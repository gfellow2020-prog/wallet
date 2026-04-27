<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashbackTransaction;

class CashbackController extends Controller
{
    public function index()
    {
        $cashbacks = CashbackTransaction::with(['user', 'merchant', 'order'])
            ->when(request('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(25);

        return view('admin.cashbacks.index', compact('cashbacks'));
    }
}
