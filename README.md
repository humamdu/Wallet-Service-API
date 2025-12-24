# Wallet Service API

A small REST API that simulates a Wallet Service. Built with Laravel and designed to demonstrate wallet operations (create wallet, deposit, withdraw, transfer) with ledger entries and idempotency support.

---

## Overview

This API provides:
- Create and retrieve wallets
- Deposit and withdraw funds (with balance checks)
- Atomic transfer between wallets (locks rows to avoid race conditions)
- Ledger entries for every operation grouped by `group_id`
- Idempotency support via `Idempotency-Key` header to prevent duplicate execution

---

## Tech stack

- Laravel 12.43.1
- PHP 8.2.12
- MySQL
- Composer, PHPUnit (for tests)
- Postman collection available under `postman/` in the repository

---

## Requirements

- PHP 8.2.12+
- Composer
- MySQL server

---

## Installation (local)

1. Clone repository:
```bash
git clone https://github.com/humamdu/Wallet-Service-API.git
cd Wallet-Service-API
```

2. Install PHP dependencies:
```bash
composer install
```

3. Copy `.env.example` to `.env` and configure database settings:
```bash
cp .env.example .env
# edit .env -> DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
php artisan key:generate
```

4. Run migrations:
```bash
php artisan migrate
```

5. Run the app:
```bash
php artisan serve
# default: http://127.0.0.1:8000
```

If seeds exist, run:
```bash
php artisan db:seed
```

---

## Amount representation

All monetary values are stored in minor units (integer). For example:
- $10.50 is stored as `1050` (cents).
- API accepts decimal amounts (e.g., 10.50) which the server converts to minor units.

The service contains a helper `toMinorUnits()` that converts decimals to integers. For production systems, consider using a money library or `bcmath` to avoid floating point rounding issues.

---

## Postman collection

A Postman collection is available in the `postman/` directory. Import it into Postman and adjust environment variables (e.g., `base_url`) as needed.

or [shared postman collection](https://absy789-7054597.postman.co/workspace/humam-do's-Workspace~92e4430a-c26f-4eaa-8a70-d7a42ef12e5f/collection/50993102-cfc0e1ec-fcfa-4bf9-9266-82116087559c?action=share&creator=50993102)

---

## Main endpoints (examples)

Assume `BASE_URL = http://127.0.0.1:8000`.

1) Create wallet
- POST /wallets
- Body (JSON):
```json
{
  "owner_name": "Ahmad",
  "currency": "USD",
  "initial_balance": 0.00
}
```
- Response (201):
```json
{
  "id": 1,
  "owner_name": "Ahmad",
  "balance": 0,
  "currency": "USD",
  "created_at": "...",
  "updated_at": "..."
}
```
Note: `balance` is in minor units (cents). Convert to decimal on client side: `balance / 100`.

2) Get wallet
- GET /wallets/{id}
- Response (200):
```json
{
  "id": 1,
  "owner_name": "Ahmad",
  "balance": 1250,
  "currency": "USD",
  "created_at": "...",
  "updated_at": "..."
}
```

3) Deposit
- POST /wallets/{id}/deposit
- Headers:
  - `Content-Type: application/json`
  - `Idempotency-Key: <unique-key>`
- Body:
```json
{
  "amount": 10.50
}
```
- cURL example:
```bash
curl -X POST "http://127.0.0.1:8000/wallets/1/deposit" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: deposit-req-001" \
  -d '{"amount":10.50}'
```
- Response (201 or 200):
```json
[
  {
    "id": 5,
    "group_id": "uuid-group",
    "wallet_id": 1,
    "type": "deposit",
    "amount": 1050,
    "currency": "USD",
    "related_wallet_id": null,
    "idempotency_key": "deposit-req-001",
    "created_at": "...",
    "updated_at": "..."
  }
]
```

4) Withdraw
- POST /wallets/{id}/withdraw
- Headers:
  - `Idempotency-Key: testWithdraw`
- Body:
```json
{
  "amount": 5.25
}
```
- Response (200):
```json
[
  {
    "id": 6,
    "group_id": "uuid-group",
    "wallet_id": 1,
    "type": "withdrawal",
    "amount": 525,
    "currency": "USD",
    "related_wallet_id": null,
    "idempotency_key": "xyz-789",
    "created_at": "...",
    "updated_at": "..."
  }
]
```
- Error (insufficient funds):
```json
{
  "message": "Insufficient funds"
}
```

5) Transfer (between wallets)
- POST /transfers
- Headers:
  - `Content-Type: application/json`
  - `Idempotency-Key: testTransfer`
- Body:
```json
{
  "source_wallet_id": 1,
  "target_wallet_id": 2,
  "amount": 20.00
}
```
- Response (200):
```json
[
  {
    "id": 10,
    "group_id": "uuid-group",
    "wallet_id": 1,
    "type": "transfer_debit",
    "amount": 2000,
    "currency": "USD",
    "related_wallet_id": 2,
    "idempotency_key": "transfer-123",
    "created_at": "...",
    "updated_at": "..."
  },
  {
    "id": 11,
    "group_id": "uuid-group",
    "wallet_id": 2,
    "type": "transfer_credit",
    "amount": 2000,
    "currency": "USD",
    "related_wallet_id": 1,
    "idempotency_key": "transfer-123",
    "created_at": "...",
    "updated_at": "..."
  }
]
```
Notes:
- Transfer is atomic and locks rows deterministically to avoid deadlocks.
- Source and target must have the same currency.

6) Query ledger entries by group
- GET /ledger-entries?group_id=<uuid-group>
- Response (200): JSON array of ledger entries for that group

---

## Idempotency behavior

- Supply an `Idempotency-Key` header for POST endpoints where duplicate execution must be prevented (deposit, withdraw, transfer).
- Expected behavior:
  - If the same key is reused for a previously completed request, the API should return the same result and NOT execute the operation again.
  - The repository includes an `idempotency_keys` model/table where responses can be recorded.

Recommendations:
- Add a UNIQUE index on the `key` column in the `idempotency_keys` table to prevent races.
- Use an atomic "create-or-get" pattern (or insert-ignore + handle duplicate key exception) so that concurrent requests with the same Idempotency-Key do not lead to duplicate wallet operations.

---

## Error handling

Typical error responses:
- Invalid amount (<= 0) → 422 Unprocessable Content with an explanatory message
- empty amount → 422 Unprocessable Content with aan explanatory message
- Insufficient funds → 422  Unprocessable Content with message
- Currency mismatch on transfer → 422 Unprocessable Content with message

---

## important notes

1. Idempotency races
   - Current logic checks for an existing idempotency record before the transaction and creates a record after. Concurrent requests can bypass the initial check and both execute.
   - Fix by creating an idempotency record atomically (inside transaction or handling duplicate key error) at the start of processing.

2. Decimal to minor unit conversion
   - Current conversion uses float math; consider `bcmath` or a dedicated money library (e.g., Brick\Money) to avoid precision issues.

---