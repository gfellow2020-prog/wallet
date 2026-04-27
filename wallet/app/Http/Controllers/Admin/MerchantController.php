<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use Illuminate\Http\Request;

class MerchantController extends Controller
{
    public function index()
    {
        $merchants = Merchant::withCount('orders')
            ->when(request('q'), fn ($q, $s) => $q->where('name', 'like', "%$s%")
            )
            ->latest()
            ->paginate(25);

        return view('admin.merchants.index', compact('merchants'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:20|unique:merchants,code',
            'category' => 'nullable|string|max:100',
            'cashback_rate' => 'nullable|numeric|min:0|max:1',
        ]);

        $code = $data['code'] ?? Merchant::makeUniqueCodeFromName($data['name']);

        Merchant::create([
            'name' => $data['name'],
            'code' => strtoupper($code),
            'category' => $data['category'] ?? null,
            'cashback_rate' => (float) ($data['cashback_rate'] ?? 0.02),
            'cashback_eligible' => $request->boolean('cashback_eligible', true),
            'is_active' => true,
        ]);

        return back()->with('success', 'Merchant created.');
    }

    public function toggle(Merchant $merchant)
    {
        $merchant->update(['is_active' => ! $merchant->is_active]);

        return back()->with('success', 'Merchant status updated.');
    }
}
