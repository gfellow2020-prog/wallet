<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FraudFlag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;

class FraudController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', FraudFlag::class);

        $status = $request->get('status', 'open');
        $q = $request->get('q');

        $query = FraudFlag::with(['user', 'reviewer'])->orderByDesc('id');

        if ($status === 'open') {
            $query->whereNull('resolved_at');
        } elseif ($status === 'resolved') {
            $query->whereNotNull('resolved_at');
        }

        if (is_string($q) && trim($q) !== '') {
            $s = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($q)).'%';
            $query->whereHas('user', function ($u) use ($s) {
                $u->where('email', 'like', $s)
                    ->orWhere('name', 'like', $s);
            });
        }

        $flags = $query->paginate(25)->withQueryString();

        return view('admin.fraud.index', compact('flags', 'status', 'q'));
    }

    public function resolve(Request $request, FraudFlag $flag)
    {
        Gate::authorize('resolve', $flag);

        $admin = $request->user();
        $flag->update([
            'resolved_at' => now(),
            'status' => 'resolved',
            'reviewed_by' => $admin?->id,
        ]);

        Log::info('fraud_flag.resolved', [
            'flag_id' => $flag->id,
            'user_id' => $flag->user_id,
            'admin_id' => $admin?->id,
        ]);

        return back()->with('success', 'Fraud flag marked as resolved.');
    }
}
