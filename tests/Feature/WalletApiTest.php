<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\Wallet;
use App\Models\LedgerEntry;

class WalletApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint()
    {
        $this->getJson('/api/health')
            ->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }

    public function test_create_wallet_and_get_balance()
    {
        $resp = $this->postJson('/api/wallets', [
            'owner_name' => 'Ali',
            'currency' => 'usd',
        ]);

        $resp->assertStatus(201)
             ->assertJsonFragment(['owner_name' => 'Ali', 'currency' => 'USD']);

        $id = $resp->json('id');

        $this->getJson("/api/wallets/{$id}/balance")
            ->assertStatus(200)
            ->assertJson(['balance' => 0, 'currency' => 'USD']);
    }

    public function test_deposit_endpoint_and_idempotency()
    {
        $wallet = Wallet::create([
            'owner_name' => 'User',
            'currency' => 'USD',
            'balance' => 0,
        ]);

        $idempotencyKey = 'api-deposit-1';

        $this->postJson("/api/wallets/{$wallet->id}/deposit", ['amount' => '10.00'], ['Idempotency-Key' => $idempotencyKey])
            ->assertStatus(201)
            ->assertJsonPath('transactions.0.amount', 1000);

        $wallet->refresh();
        $this->assertEquals(1000, $wallet->balance);

        // repeat same idempotency key
        $this->postJson("/api/wallets/{$wallet->id}/deposit", ['amount' => '10.00'], ['Idempotency-Key' => $idempotencyKey])
            ->assertStatus(201);

        $wallet->refresh();
        $this->assertEquals(1000, $wallet->balance, 'idempotent deposit must not double the balance');
    }

    public function test_withdraw_endpoint_rejects_insufficient()
    {
        $wallet = Wallet::create([
            'owner_name' => 'W',
            'currency' => 'USD',
            'balance' => 500,
        ]);

        $this->postJson("/api/wallets/{$wallet->id}/withdraw", ['amount' => '10.00'], ['Idempotency-Key' => 'w1'])
            ->assertStatus(500); // service throws RuntimeException -> by default returns 500 unless handled

        // ensure balance unchanged
        $wallet->refresh();
        $this->assertEquals(500, $wallet->balance);
    }

    public function test_transfer_endpoint_and_transactions_listing_with_filters()
    {
        $a = Wallet::create(['owner_name' => 'A', 'currency' => 'USD', 'balance' => 5000]);
        $b = Wallet::create(['owner_name' => 'B', 'currency' => 'USD', 'balance' => 0]);

        $this->postJson('/api/transfers', [
            'source_wallet_id' => $a->id,
            'target_wallet_id' => $b->id,
            'amount' => '25.50',
        ], ['Idempotency-Key' => 't1'])
        ->assertStatus(201)
        ->assertJsonPath('transactions.0.amount', 2550);

        $a->refresh();
        $b->refresh();
        $this->assertEquals(2450, $a->balance);
        $this->assertEquals(2550, $b->balance);

        // transactions listing: ensure both debit and credit appear for respective wallets
        $this->getJson("/api/wallets/{$a->id}/transactions")
            ->assertStatus(200)
            ->assertJsonFragment(['type' => 'transfer_debit']);

        $this->getJson("/api/wallets/{$b->id}/transactions?type=transfer_credit")
            ->assertStatus(200)
            ->assertJsonFragment(['type' => 'transfer_credit']);
    }

    public function test_transactions_pagination_and_date_filters()
    {
        $w = Wallet::create(['owner_name' => 'P', 'currency' => 'USD', 'balance' => 0]);

        // create several ledger entries manually
        LedgerEntry::create([
            'group_id' => \Illuminate\Support\Str::uuid()->toString(),
            'wallet_id' => $w->id,
            'type' => 'deposit',
            'amount' => 100,
            'currency' => 'USD',
            'related_wallet_id' => null,
            'idempotency_key' => null,
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        LedgerEntry::create([
            'group_id' => \Illuminate\Support\Str::uuid()->toString(),
            'wallet_id' => $w->id,
            'type' => 'deposit',
            'amount' => 200,
            'currency' => 'USD',
            'related_wallet_id' => null,
            'idempotency_key' => null,
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subDays(1),
        ]);

        // filter by date range to only include the recent one
        $from = now()->subDays(2)->toDateString();
        $to = now()->toDateString();

        $resp = $this->getJson("/api/wallets/{$w->id}/transactions?from={$from}&to={$to}&per_page=10");

        $resp->assertStatus(200)
             ->assertJsonPath('data.0.amount', 200)
             ->assertJsonMissing(['amount' => 100]);
    }
}
