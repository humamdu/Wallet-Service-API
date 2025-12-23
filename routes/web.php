<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\WalletController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\HealthController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api/health', [HealthController::class, 'index']);

Route::prefix('/api/wallets')->group(function () {
    Route::post('/', [WalletController::class, 'store']);
    Route::get('/', [WalletController::class, 'index']);
    Route::get('/{id}', [WalletController::class, 'show']);
    Route::get('/{id}/balance', [WalletController::class, 'balance']);
    Route::get('/{id}/transactions', [WalletController::class, 'transactions']);

    Route::post('/{id}/deposit', [App\Http\Controllers\TransactionController::class, 'deposit']);
    Route::post('/{id}/withdraw', [App\Http\Controllers\TransactionController::class, 'withdraw']);
});

Route::post('/api/transfers', [TransferController::class, 'transfer']);
