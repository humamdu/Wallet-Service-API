<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LedgerEntry extends Model
{
    use HasFactory;

    // groups_id : group debit+credit for same operation
    // type: deposit, withdraw, transfer_debit, transfer_credit
    protected $fillable = [
        'group_id', 'wallet_id', 'type', 'amount', 'currency', 'related_wallet_id', 'idempotency_key'
    ];

    protected $casts = [
        'amount' => 'integer', // always positive (minor units)
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function relatedWallet()
    {
        return $this->belongsTo(Wallet::class, 'related_wallet_id');
    }
}