<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

use App\Models\Wallet;

class CasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_negative_and_zero_amounts_are_rejected()
    {
        $w = Wallet::create(['owner_name' => 'V', 'currency' => 'USD', 'balance' => 1000]);

        // zero
        $this->postJson("/api/wallets/{$w->id}/deposit", ['amount' => '0.00'], ['Idempotency-Key' => 'n1'])
            ->assertStatus(500); // InvalidArgumentException thrown in service -> 500 unless app handles it

        // negative
        $this->postJson("/api/wallets/{$w->id}/withdraw", ['amount' => '-5.00'], ['Idempotency-Key' => 'n2'])
            ->assertStatus(500); // validation rejects negative numeric format? Our OperationRequest only checks numeric, controller converts and service rejects with InvalidArgumentException
    }

    public function test_create_wallet_validation()
    {
        // missing currency
        $this->postJson('/api/wallets', ['owner_name' => 'Z'])
            ->assertStatus(422);

        // invalid currency length
        $this->postJson('/api/wallets', ['owner_name' => 'Z', 'currency' => 'US'])
            ->assertStatus(422);
    }
}
