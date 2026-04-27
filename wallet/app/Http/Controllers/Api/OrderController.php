<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * List the authenticated user's orders (latest first, paginated).
     */
    public function index(Request $request): JsonResponse
    {
        $orders = Order::with('merchant:id,name,code,category,cashback_eligible')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json($orders->through(fn ($o) => $this->formatOrder($o)));
    }

    /**
     * Create a new order (pre-payment step).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'merchant_id' => ['required', 'integer', 'exists:merchants,id'],
            'gross_amount' => ['required', 'numeric', 'min:1'],
        ]);

        $merchant = Merchant::findOrFail($request->merchant_id);

        abort_if(! $merchant->is_active, 422, 'Merchant is not available.');

        // Fee: flat 1.5 % of gross, rounded to 2dp
        $grossAmount = round((float) $request->gross_amount, 2);
        $feeAmount = round($grossAmount * 0.015, 2);
        $eligibleAmount = $merchant->cashback_eligible
            ? round($grossAmount - $feeAmount, 2)
            : 0.00;

        $order = Order::create([
            'user_id' => $request->user()->id,
            'merchant_id' => $merchant->id,
            'order_reference' => 'ORD-'.strtoupper(Str::random(10)),
            'gross_amount' => $grossAmount,
            'eligible_amount' => $eligibleAmount,
            'fee_amount' => $feeAmount,
            'currency' => 'ZMW',
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Order created. Proceed to payment.',
            'order' => $this->formatOrder($order->load('merchant')),
        ], 201);
    }

    /**
     * Show a single order (must belong to auth user).
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        abort_if($order->user_id !== $request->user()->id, 403, 'Forbidden.');

        return response()->json([
            'order' => $this->formatOrder($order->load('merchant', 'payments')),
        ]);
    }

    private function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_reference' => $order->order_reference,
            'gross_amount' => (float) $order->gross_amount,
            'eligible_amount' => (float) $order->eligible_amount,
            'fee_amount' => (float) $order->fee_amount,
            'currency' => $order->currency,
            'status' => $order->status,
            'merchant' => $order->merchant ? [
                'id' => $order->merchant->id,
                'name' => $order->merchant->name,
                'code' => $order->merchant->code,
                'category' => $order->merchant->category,
                'cashback_eligible' => $order->merchant->cashback_eligible,
            ] : null,
            'created_at' => $order->created_at?->toISOString(),
        ];
    }
}
