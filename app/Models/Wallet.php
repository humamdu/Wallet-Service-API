<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = ['owner_name', 'currency', 'balance', 'uuid'];

    protected $casts = [
        'balance' => 'integer',
    ];

    public static function booted()
    {
        static::creating(fn($model) => $model->uuid = $model->uuid ?? \Illuminate\Support\Str::uuid()->toString());
    }

    public function ledgerEntries()
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
