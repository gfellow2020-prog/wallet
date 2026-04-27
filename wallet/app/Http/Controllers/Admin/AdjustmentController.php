<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAdjustment;

class AdjustmentController extends Controller
{
    public function index()
    {
        $adjustments = AdminAdjustment::query()
            ->with(['user', 'wallet', 'admin'])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.adjustments.index', compact('adjustments'));
    }
}
