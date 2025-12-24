<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\WalletController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\TransactionController;
use App\Http\Middleware\RequireIdempotencyKey;


Route::get('/health', [HealthController::class, 'index']);

Route::prefix('/wallets')->group(function () {
    Route::post('/', [WalletController::class, 'store']);
    Route::get('/', [WalletController::class, 'index']);
    Route::get('/{id}', [WalletController::class, 'show']);
    Route::get('/{id}/balance', [WalletController::class, 'balance']);
    Route::get('/{id}/transactions', [WalletController::class, 'transactions']);

    Route::post('/{id}/deposit', [TransactionController::class, 'deposit'])
                ->middleware(RequireIdempotencyKey::class);
    Route::post('/{id}/withdraw', [TransactionController::class, 'withdraw'])
                ->middleware(RequireIdempotencyKey::class);
});

Route::post('/transfers', [TransferController::class, 'transfer'])
            ->middleware(RequireIdempotencyKey::class);
