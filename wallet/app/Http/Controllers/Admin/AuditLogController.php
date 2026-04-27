<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index()
    {
        $logs = AuditLog::query()
            ->with('user')
            ->when(request('q'), function ($q, $search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('action', 'like', "%{$search}%")
                        ->orWhere('auditable_type', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit.index', compact('logs'));
    }
}
