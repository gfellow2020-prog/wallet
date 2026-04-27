<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CheckoutService;
use App\Services\RewardsEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    public function __construct(
        private CheckoutService $checkout,
        private RewardsEngine $rewards,
    ) {}

    /**
     * POST /api/checkout
     *
     * Debits the buyer's wallet for the full cart gross total,
     * credits each seller 97%, credits 2% cashback back to the buyer,
     * decrements stock, and clears the cart — all in one DB transaction.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $result = $this->checkout->checkout($request->user());
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        try {
            $this->rewards->recordAction($request->user(), 'buy_product', [
                'source_type' => 'cart_checkout',
                'source_id' => $result['reference'],
                'sale_ids' => $result['sale_ids'] ?? [],
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        Log::info('checkout.completed', [
            'order_reference' => $result['reference'],
            'total_paid' => $result['totals']['gross'] ?? null,
            'sale_ids' => $result['sale_ids'] ?? [],
        ]);

        return response()->json([
            'message' => 'Order placed successfully! Cashback has been credited to your wallet.',
            'order' => [
                'reference' => $result['reference'],
                'lines' => $result['lines'],
                'total_paid' => $result['totals']['gross'],
                'cashback_earned' => $result['totals']['cashback'],
                'net_paid' => $result['totals']['net_paid'],
                'currency' => 'ZMW',
            ],
        ], 201);
    }
}
