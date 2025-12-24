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
        $amount = $this->service->toMinorUnits($request->input('amount'));
        $idempotencyKey = $request->header('Idempotency-Key');

        try {

            $entries = $this->service->transfer($source, $target, $amount, $idempotencyKey);

        } catch (\Throwable $th) {

            return response()->json(['error' => $th->getMessage()], 422);
            
        }


        return response()->json(['transactions' => $entries->toArray()], 201);
    }

}
