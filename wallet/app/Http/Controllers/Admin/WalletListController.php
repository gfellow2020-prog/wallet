<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Wallet;

class WalletListController extends Controller
{
    public function index()
    {
        $wallets = Wallet::query()
            ->with('user')
            ->when(request('q'), function ($q, $search) {
                $q->whereHas('user', function ($u) use ($search) {
                    $u->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.wallets.index', compact('wallets'));
    }
}
