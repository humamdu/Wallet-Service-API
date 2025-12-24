<?php

namespace App\Http\Controllers;

use App\Http\Requests\OperationRequest;
use App\Models\Wallet;
use App\Services\WalletService;

class TransactionController extends Controller
{
    protected WalletService $service;

    public function __construct(WalletService $service)
    {
        $this->service = $service;
    }

    // deposit
    public function deposit(OperationRequest $request, $id)
    {
        $wallet = Wallet::findOrFail($id);
        $amount = $this->service->toMinorUnits($request->input('amount'));
        $idempotencyKey = $request->header('Idempotency-Key');

        try {
            

            $entries = $this->service->deposit($wallet, $amount, $idempotencyKey);

        } catch (\Throwable $th) {

            return response()->json(['error' => $th->getMessage()], 422);
            
        }

        return response()->json(['transactions' => $entries->toArray()], 201);
    }

    // withdraw
    public function withdraw(OperationRequest $request, $id)
    {
        $wallet = Wallet::findOrFail($id);
        $amount = $this->service->toMinorUnits($request->input('amount'));
        $idempotencyKey = $request->header('Idempotency-Key');

        try {

            $entries = $this->service->withdraw($wallet, $amount, $idempotencyKey);

        } catch (\Throwable $th) {

            return response()->json(['error' => $th->getMessage()], 422);
            
        }

        return response()->json(['transactions' => $entries->toArray()], 201);
    }

}