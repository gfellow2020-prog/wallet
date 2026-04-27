<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\ProductSaleFeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        protected ProductSaleFeeService $fees,
    ) {}

    /**
     * GET /api/cart
     */
    public function index(Request $request): JsonResponse
    {
        $items = CartItem::with(['product' => fn ($q) => $q->with('seller:id,name')])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($this->serialize($items));
    }

    /**
     * POST /api/cart
     * body: { product_id, quantity? (default 1) }
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'nullable|integer|min:1|max:99',
        ]);

        $product = Product::find($data['product_id']);

        if (! $product || ! $product->is_active) {
            return response()->json(['message' => 'This product is no longer available.'], 422);
        }
        if ($product->user_id === $request->user()->id) {
            return response()->json(['message' => 'You cannot add your own listing to your cart.'], 422);
        }

        $qty = (int) ($data['quantity'] ?? 1);

        $item = CartItem::where('user_id', $request->user()->id)
            ->where('product_id', $product->id)
            ->first();

        $newQty = ($item?->quantity ?? 0) + $qty;

        if ($newQty > $product->stock) {
            return response()->json([
                'message' => 'Only '.$product->stock.' unit(s) of this product are available.',
            ], 422);
        }

        $item = CartItem::updateOrCreate(
            ['user_id' => $request->user()->id, 'product_id' => $product->id],
            ['quantity' => $newQty]
        );

        $items = CartItem::with(['product' => fn ($q) => $q->with('seller:id,name')])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json(array_merge(
            $this->serialize($items),
            ['message' => 'Added to cart.']
        ), 201);
    }

    /**
     * PATCH /api/cart/{item}
     * body: { quantity }
     */
    public function update(Request $request, CartItem $item): JsonResponse
    {
        abort_if($item->user_id !== $request->user()->id, 403);

        $data = $request->validate([
            'quantity' => 'required|integer|min:0|max:99',
        ]);

        if ((int) $data['quantity'] === 0) {
            $item->delete();
        } else {
            $product = $item->product;
            if (! $product || ! $product->is_active) {
                return response()->json(['message' => 'This product is no longer available.'], 422);
            }
            if ($data['quantity'] > $product->stock) {
                return response()->json([
                    'message' => 'Only '.$product->stock.' unit(s) of this product are available.',
                ], 422);
            }
            $item->update(['quantity' => (int) $data['quantity']]);
        }

        $items = CartItem::with(['product' => fn ($q) => $q->with('seller:id,name')])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json($this->serialize($items));
    }

    /**
     * DELETE /api/cart/{item}
     */
    public function destroy(Request $request, CartItem $item): JsonResponse
    {
        abort_if($item->user_id !== $request->user()->id, 403);

        $item->delete();

        $items = CartItem::with(['product' => fn ($q) => $q->with('seller:id,name')])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json(array_merge(
            $this->serialize($items),
            ['message' => 'Item removed.']
        ));
    }

    /**
     * DELETE /api/cart  — empty cart
     */
    public function clear(Request $request): JsonResponse
    {
        CartItem::where('user_id', $request->user()->id)->delete();

        return response()->json($this->serialize(collect()));
    }

    /**
     * Build the standard cart payload with item details + totals.
     */
    private function serialize($items): array
    {
        $lines = $items->map(function (CartItem $i) {
            $product = $i->product;
            $unit = $product ? (float) $product->price : 0.0;
            $qty = (int) $i->quantity;
            $line = round($unit * $qty, 2);

            $imageUrl = null;
            if ($product && $product->image_url) {
                $imageUrl = str_starts_with($product->image_url, 'http')
                    ? $product->image_url
                    : url('/storage/'.ltrim($product->image_url, '/'));
            }

            return [
                'id' => $i->id,
                'quantity' => $qty,
                'unit_price' => $unit,
                'line_total' => $line,
                'cashback_earned' => $this->fees->cashbackFor($line),
                'in_stock' => $product ? (int) $product->stock : 0,
                'available' => $product ? (bool) $product->is_active : false,
                'product' => $product ? [
                    'id' => $product->id,
                    'title' => $product->title,
                    'price' => (float) $product->price,
                    'stock' => (int) $product->stock,
                    'category' => $product->category,
                    'condition' => $product->condition,
                    'location_label' => $product->location_label,
                    'image_url' => $imageUrl,
                    'seller' => $product->seller ? [
                        'id' => $product->seller->id,
                        'name' => $product->seller->name,
                    ] : null,
                ] : null,
            ];
        })->values();

        $gross = round((float) $lines->sum('line_total'), 2);

        return [
            'items' => $lines,
            'totals' => [
                'item_count' => (int) $lines->sum('quantity'),
                'line_count' => $lines->count(),
                'gross' => $gross,
                'cashback' => $this->fees->cashbackFor($gross),
                'net_after_cashback' => $this->fees->netAfterCashback($gross),
            ],
        ];
    }
}
