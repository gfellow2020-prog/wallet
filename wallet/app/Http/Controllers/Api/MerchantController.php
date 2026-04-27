<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MerchantController extends Controller
{
    /**
     * List all active merchants (optionally filtered by category).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Merchant::where('is_active', true);

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }

        $perPage = max(1, min((int) $request->integer('per_page', 50), 100));
        $page = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'merchants' => collect($page->items())->map(fn (Merchant $m) => $this->formatMerchant($m)),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * Show a single merchant.
     */
    public function show(Merchant $merchant): JsonResponse
    {
        abort_if(! $merchant->is_active, 404, 'Merchant not found.');

        return response()->json([
            'merchant' => $this->formatMerchant($merchant),
        ]);
    }

    /**
     * Admin: create a merchant.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:20', 'unique:merchants,code'],
            'category' => ['required', 'string', 'max:50'],
            'cashback_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'cashback_eligible' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $code = $request->filled('code')
            ? strtoupper($request->string('code'))
            : Merchant::makeUniqueCodeFromName($request->name);

        $merchant = Merchant::create([
            'name' => $request->name,
            'code' => $code,
            'category' => $request->category,
            'cashback_rate' => (float) ($request->input('cashback_rate', 0.02)),
            'cashback_eligible' => $request->boolean('cashback_eligible', true),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'message' => 'Merchant created.',
            'merchant' => $this->formatMerchant($merchant),
        ], 201);
    }

    /**
     * Admin: update a merchant.
     */
    public function update(Request $request, Merchant $merchant): JsonResponse
    {
        $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'category' => ['sometimes', 'string', 'max:50'],
            'cashback_rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'cashback_eligible' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $merchant->update($request->only(['name', 'category', 'cashback_rate', 'cashback_eligible', 'is_active']));

        return response()->json([
            'message' => 'Merchant updated.',
            'merchant' => $this->formatMerchant($merchant->fresh()),
        ]);
    }

    private function formatMerchant(Merchant $m): array
    {
        $rate = (float) ($m->cashback_rate ?? 0.02);

        return [
            'id' => $m->id,
            'name' => $m->name,
            'code' => $m->code,
            'category' => $m->category,
            'cashback_rate' => $rate,
            'cashback_eligible' => $m->cashback_eligible,
            'is_active' => $m->is_active,
        ];
    }
}
