<?php

namespace Tests\Unit;

use Tests\TestCase;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Wallet;
use App\Models\LedgerEntry;
use App\Services\WalletService;
use Illuminate\Support\Str;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WalletService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(WalletService::class);
    }

    public function test_deposit_updates_balance_and_is_idempotent()
    {
        $wallet = Wallet::create([
            'owner_name' => 'Test',
            'currency' => 'USD',
            'balance' => 0,
        ]);

        $idempotencyKey = 'idem-deposit-1';

        $entriesFirst = $this->service->deposit($wallet, 1000, $idempotencyKey);
        $wallet->refresh();
        $this->assertEquals(1000, $wallet->balance);
        $this->assertCount(1, $entriesFirst);

        // repeat with same idempotency key -> should not double the balance
        $entriesSecond = $this->service->deposit($wallet, 1000, $idempotencyKey);
        $wallet->refresh();
        $this->assertEquals(1000, $wallet->balance, 'Balance must not change on repeated idempotent deposit');
        $this->assertEquals($entriesFirst->first()->group_id, $entriesSecond->first()->group_id);
    }

    public function test_withdraw_fails_when_insufficient_funds()
    {
        $wallet = Wallet::create([
            'owner_name' => 'W',
            'currency' => 'USD',
            'balance' => 500, // minor units
        ]);
        $idempotencyKey = 'idem-withdraw-1';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient funds');

        $this->service->withdraw($wallet, 1000, $idempotencyKey);
    }

    public function test_transfer_creates_debit_and_credit_and_is_atomic()
    {
        $source = Wallet::create([
            'owner_name' => 'Source',
            'currency' => 'USD',
            'balance' => 5000,
        ]);

        $target = Wallet::create([
            'owner_name' => 'Target',
            'currency' => 'USD',
            'balance' => 1000,
        ]);

        $idempotencyKey = 'transfer-abc-1';
        $entries = $this->service->transfer($source, $target, 2000, $idempotencyKey);

        $source->refresh();
        $target->refresh();

        // balances updated
        $this->assertEquals(3000, $source->balance);
        $this->assertEquals(3000, $target->balance);

        // two ledger entries (debit + credit) with same group_id
        $this->assertCount(2, $entries);
        $this->assertEquals($entries[0]->group_id, $entries[1]->group_id);

        // double-entry specifics
        $debit = collect($entries)->firstWhere('type', 'transfer_debit');
        $credit = collect($entries)->firstWhere('type', 'transfer_credit');

        $this->assertNotNull($debit);
        $this->assertNotNull($credit);
        $this->assertEquals($debit->amount, 2000);
        $this->assertEquals($credit->amount, 2000);
    }

    public function test_transfer_rejects_self_transfer_and_currency_mismatch()
    {
        $w = Wallet::create([
            'owner_name' => 'Self',
            'currency' => 'USD',
            'balance' => 1000,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot transfer to same wallet');

        $idempotencyKey = "test-transfer-self";

        $this->service->transfer($w, $w, 100, $idempotencyKey);

        // currency mismatch
        $a = Wallet::create([
            'owner_name' => 'A',
            'currency' => 'USD',
            'balance' => 1000,
        ]);
        $b = Wallet::create([
            'owner_name' => 'B',
            'currency' => 'EUR',
            'balance' => 1000,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency mismatch');

        $idempotencyKey = "test-transfer-missmatch";
        $this->service->transfer($a, $b, 100, $idempotencyKey);
    }
}