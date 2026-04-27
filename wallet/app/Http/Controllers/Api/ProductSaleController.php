<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductSale;
use App\Services\ProductSaleService;
use App\Services\RewardsEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductSaleController extends Controller
{
    public function __construct(
        private ProductSaleService $saleService,
        private RewardsEngine $rewards,
    ) {}

    /**
     * POST /api/products/{product}/buy
     *
     * Business rules:
     *   - Buyer pays the full listed price (gross_amount)
     *   - 2% of gross is credited to the buyer's ExtraCash wallet as cashback
     *   - 1% of gross is the platform admin fee
     *   - Seller receives 97% of the gross amount
     */
    public function buy(Request $request, Product $product): JsonResponse
    {
        try {
            $sale = $this->saleService->purchase($product, $request->user()->id);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        try {
            $this->rewards->recordAction($request->user(), 'buy_product', [
                'source_type' => ProductSale::class,
                'source_id' => $sale->id,
                'product_id' => $product->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'message' => 'Purchase successful! Cashback has been credited to your wallet.',
            'sale' => [
                'reference' => $sale->reference,
                'product' => $product->title,
                'you_paid' => $sale->gross_amount,
                'cashback_earned' => $sale->cashback_amount,
                'cashback_rate' => '2%',
                'currency' => 'ZMW',
            ],
        ], 201);
    }
}
