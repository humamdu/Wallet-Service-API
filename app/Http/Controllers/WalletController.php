<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWalletRequest;
use Illuminate\Http\Request;
use App\Models\Wallet;
use App\Services\WalletService;
use App\Models\LedgerEntry;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        $query = Wallet::query();

        if ($owner = $request->query('owner')) {
            $query->where('owner_name', 'like', "%{$owner}%");
        }
        if ($currency = $request->query('currency')) {
            $query->where('currency', strtoupper($currency));
        }

        $wallets = $query->paginate(20);

        return response()->json($wallets);
    }

    public function store(StoreWalletRequest $request)
    {
        $wallet = Wallet::create([
            'owner_name' => $request->input('owner_name'),
            'currency' => strtoupper($request->input('currency')),
            'balance' => 0,
        ]);

        return response()->json($wallet, 201);
    }

    public function show($id)
    {
        $wallet = Wallet::findOrFail($id);
        return response()->json($wallet);
    }

    public function balance($id)
    {
        $wallet = Wallet::findOrFail($id);
        return response()->json(['balance' => $wallet->balance, 'currency' => $wallet->currency]);
    }

    public function transactions(Request $request, $id)
    {
        $wallet = Wallet::findOrFail($id);

        $query = $wallet->ledgerEntries()->orderBy('created_at', 'desc');

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('created_at', '<=', $to);
        }

        $perPage = min(100, (int) $request->query('per_page', 20));
        $tx = $query->paginate($perPage);

        return response()->json($tx);
    }
}
