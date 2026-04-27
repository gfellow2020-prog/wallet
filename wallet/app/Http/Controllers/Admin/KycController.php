<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KycRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class KycController extends Controller
{
    public function index()
    {
        Gate::authorize('viewAny', KycRecord::class);

        $status = request('status', 'all');

        $query = KycRecord::with('user')->latest();

        if ($status !== 'all') {
            $query->where('status', ucfirst($status));
        }

        $records = $query->paginate(25);

        $counts = [
            'pending' => KycRecord::where('status', 'Pending')->count(),
            'verified' => KycRecord::where('status', 'Verified')->count(),
            'rejected' => KycRecord::where('status', 'Rejected')->count(),
            'total' => KycRecord::count(),
        ];

        return view('admin.kyc.index', compact('records', 'counts'));
    }

    public function show(KycRecord $kyc)
    {
        Gate::authorize('view', $kyc);

        $kyc->load('user');

        return view('admin.kyc.show', compact('kyc'));
    }

    public function review(Request $request, KycRecord $kyc)
    {
        Gate::authorize('review', $kyc);

        $request->validate(['action' => 'required|in:approve,reject']);

        $adminId = (int) ($request->user()?->id ?? 0);

        $kyc->update([
            'status' => $request->action === 'approve' ? 'Verified' : 'Rejected',
            'reviewed_at' => now(),
            'reviewed_by' => $adminId ?: null,
            'notes' => $request->notes,
        ]);

        return back()->with('success', 'KYC record updated.');
    }
}
