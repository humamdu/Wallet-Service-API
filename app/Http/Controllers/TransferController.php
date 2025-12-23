<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransferRequest;
use Illuminate\Http\Request;
use App\Models\Wallet;
use App\Services\WalletService;

class TransferController extends Controller
{
    protected WalletService $service;

    public function __construct(WalletService $service)
    {
        $this->service = $service;
    }

    public function transfer(TransferRequest $request)
    {
        $source = Wallet::findOrFail($request->input('source_wallet_id'));
        $target = Wallet::findOrFail($request->input('target_wallet_id'));
        $amount = $this->toMinorUnits($request->input('amount'));
        $idempotencyKey = $request->header('Idempotency-Key');

        $entries = $this->service->transfer($source, $target, $amount, $idempotencyKey);

        return response()->json(['transactions' => $entries->toArray()], 201);
    }

    protected function toMinorUnits($value): int
    {
        if (is_numeric($value)) {
            return (int)round(floatval($value) * 100);
        }
        throw new \InvalidArgumentException('Invalid amount format');
    }
}
