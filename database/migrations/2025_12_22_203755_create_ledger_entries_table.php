<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('group_id')->index(); // groups debit+credit for same operation
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->enum('type', ['deposit','withdrawal','transfer_debit','transfer_credit']);
            $table->bigInteger('amount'); // always positive (minor units)
            $table->string('currency', 3);
            $table->foreignId('related_wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->string('idempotency_key')->nullable()->index();
            $table->timestamps();
            $table->index(['wallet_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
