<?php

namespace App\Services;

use App\Models\Wallet;
use App\Models\LedgerEntry;
use App\Models\IdempotencyKey;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class WalletService
{
    // amountMinor: integer (minor units)
    public function deposit(Wallet $wallet, int $amountMinor, string $idempotencyKey)
    {
        if ($amountMinor <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero');
        }

        if ($idempotencyKey) {
            $existing = IdempotencyKey::where('key', $idempotencyKey)->first();
            if ($existing && $existing->group_id) {
                $entries = LedgerEntry::where('group_id', $existing->group_id)->get();
                return $entries;
            }
        }

        $groupId = Str::uuid()->toString();

        return DB::transaction(function () use ($wallet, $amountMinor, $idempotencyKey, $groupId) {
            // lock row
            $w = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            $w->balance = $w->balance + $amountMinor;
            $w->save();

            $entry = LedgerEntry::create([
                'group_id' => $groupId,
                'wallet_id' => $w->id,
                'type' => 'deposit',
                'amount' => $amountMinor,
                'currency' => $w->currency,
                'related_wallet_id' => null,
                'idempotency_key' => $idempotencyKey,
            ]);

            if ($idempotencyKey) {
                IdempotencyKey::create([
                    'key' => $idempotencyKey,
                    'method' => 'POST',
                    'path' => '/wallets/'.$w->id.'/deposit',
                    'group_id' => $groupId,
                    'response' => ['entry_id' => $entry->id],
                ]);
            }

            return collect([$entry]);
        });
    }

    public function withdraw(Wallet $wallet, int $amountMinor, string $idempotencyKey)
    {
        if ($amountMinor <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero');
        }

        if ($idempotencyKey) {
            $existing = IdempotencyKey::where('key', $idempotencyKey)->first();
            if ($existing && $existing->group_id) {
                $entries = LedgerEntry::where('group_id', $existing->group_id)->get();
                return $entries;
            }
        }

        $groupId = Str::uuid()->toString();

        return DB::transaction(function () use ($wallet, $amountMinor, $idempotencyKey, $groupId) {
            $w = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

            if ($w->balance < $amountMinor) {
                throw new \RuntimeException('Insufficient funds');
            }

            $w->balance = $w->balance - $amountMinor;
            $w->save();

            $entry = LedgerEntry::create([
                'group_id' => $groupId,
                'wallet_id' => $w->id,
                'type' => 'withdrawal',
                'amount' => $amountMinor,
                'currency' => $w->currency,
                'related_wallet_id' => null,
                'idempotency_key' => $idempotencyKey,
            ]);

            if ($idempotencyKey) {
                IdempotencyKey::create([
                    'key' => $idempotencyKey,
                    'method' => 'POST',
                    'path' => '/wallets/'.$w->id.'/withdraw',
                    'group_id' => $groupId,
                    'response' => ['entry_id' => $entry->id],
                ]);
            }

            return collect([$entry]);
        });
    }

    // transfer between wallets atomically
    public function transfer(Wallet $source, Wallet $target, int $amountMinor, string $idempotencyKey)
    {
        if ($amountMinor <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero');
        }

        if ($source->id === $target->id) {
            throw new \InvalidArgumentException('Cannot transfer to same wallet');
        }

        if ($source->currency !== $target->currency) {
            throw new \InvalidArgumentException('Currency mismatch');
        }

        if ($idempotencyKey) {
            $existing = IdempotencyKey::where('key', $idempotencyKey)->first();
            if ($existing && $existing->group_id) {
                $entries = LedgerEntry::where('group_id', $existing->group_id)->get();
                return $entries;
            }
        }

        $groupId = Str::uuid()->toString();

        return DB::transaction(function () use ($source, $target, $amountMinor, $idempotencyKey, $groupId) {
            // lock both wallets in deterministic order to avoid deadlocks
            $firstId = min($source->id, $target->id);
            $secondId = max($source->id, $target->id);

            $first = Wallet::where('id', $firstId)->lockForUpdate()->first();
            $second = Wallet::where('id', $secondId)->lockForUpdate()->first();

            if ($first->id === $source->id) {
                $src = $first;
                $tgt = $second;
            } else {
                $src = $second;
                $tgt = $first;
            }

            if ($src->balance < $amountMinor) {
                throw new \RuntimeException('Insufficient funds in source wallet');
            }

            $src->balance -= $amountMinor;
            $src->save();

            $tgt->balance += $amountMinor;
            $tgt->save();

            $debit = LedgerEntry::create([
                'group_id' => $groupId,
                'wallet_id' => $src->id,
                'type' => 'transfer_debit',
                'amount' => $amountMinor,
                'currency' => $src->currency,
                'related_wallet_id' => $tgt->id,
                'idempotency_key' => $idempotencyKey,
            ]);

            $credit = LedgerEntry::create([
                'group_id' => $groupId,
                'wallet_id' => $tgt->id,
                'type' => 'transfer_credit',
                'amount' => $amountMinor,
                'currency' => $tgt->currency,
                'related_wallet_id' => $src->id,
                'idempotency_key' => $idempotencyKey,
            ]);

            if ($idempotencyKey) {
                IdempotencyKey::create([
                    'key' => $idempotencyKey,
                    'method' => 'POST',
                    'path' => '/transfers',
                    'group_id' => $groupId,
                    'response' => ['debit_id' => $debit->id, 'credit_id' => $credit->id],
                ]);
            }

            return collect([$debit, $credit]);
        });
    }

    public function toMinorUnits($value): int
    {
        if (is_numeric($value)) {
            return (int)round(floatval($value) * 100);
        }
        throw new \InvalidArgumentException('Invalid amount format');
    }
}