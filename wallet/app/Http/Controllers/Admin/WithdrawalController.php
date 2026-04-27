<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function index()
    {
        $withdrawals = Withdrawal::with('user')
            ->when(request('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(25);

        return view('admin.withdrawals.index', compact('withdrawals'));
    }

    public function action(Request $request, Withdrawal $withdrawal)
    {
        $request->validate(['action' => 'required|in:approve,reject']);

        $status = $request->action === 'approve' ? 'Approved' : 'Rejected';

        $withdrawal->update([
            'status' => $status,
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
        ]);

        return back()->with('success', "Withdrawal {$status}.");
    }
}
