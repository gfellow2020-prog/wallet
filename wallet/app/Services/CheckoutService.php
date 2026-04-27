<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Handles the multi-item cart checkout flow.
 *
 * Business rules (identical to single-product purchase, applied per line):
 *   - Buyer pays gross = price × quantity (wallet debit)
 *   - 2% of the gross is credited back to the buyer's wallet as cashback
 *   - 1% of the gross is the platform admin fee
 *   - Seller receives 97% of the gross
 *
 * Net buyer outflow per line = gross - cashback = 98% of gross.
 */
class CheckoutService
{
    public function __construct(
        protected ProductSaleFeeService $fees,
    ) {}

    /**
     * Checkout the authenticated user's cart.
     *
     * @return array{reference:string,lines:array<int,array<string,mixed>>,totals:array<string,float>}
     *
     * @throws \Exception on empty cart, self-purchase, stock/availability issues, insufficient funds
     */
    public function checkout(User $buyer): array
    {
        $items = CartItem::with('product')
            ->where('user_id', $buyer->id)
            ->get();

        if ($items->isEmpty()) {
            throw new \Exception('Your cart is empty.');
        }

        // ── Pre-validate items (outside transaction for friendlier errors) ──
        foreach ($items as $item) {
            /** @var Product|null $product */
            $product = $item->product;

            if (! $product) {
                throw new \Exception('One of the items in your cart is no longer available.');
            }
            if ($product->user_id === $buyer->id) {
                throw new \Exception('You cannot purchase your own listing: '.$product->title);
            }
            if (! $product->is_active || $product->stock < 1) {
                throw new \Exception($product->title.' is no longer available.');
            }
            if ($product->stock < $item->quantity) {
                throw new \Exception('Only '.$product->stock.' unit(s) of '.$product->title.' remain.');
            }
        }

        $grossTotal = (float) $items->sum(
            fn (CartItem $i) => round((float) $i->product->price * $i->quantity, 2)
        );

        $buyerWallet = Wallet::firstOrCreate(
            ['user_id' => $buyer->id],
            ['currency' => 'ZMW', 'available_balance' => 0, 'pending_balance' => 0]
        );

        if ((float) $buyerWallet->available_balance < $grossTotal) {
            throw new \Exception('Insufficient wallet balance. Please top up and try again.');
        }

        $checkoutRef = 'CHK-'.strtoupper(Str::random(12));

        return DB::transaction(function () use ($buyer, $items, $grossTotal, $checkoutRef) {
            $buyerWallet = Wallet::where('user_id', $buyer->id)->lockForUpdate()->first();

            // ── 1. Debit buyer for the full cart total (one ledger entry) ──
            $buyerBalBefore = (float) $buyerWallet->available_balance;

            if ($buyerBalBefore < $grossTotal) {
                throw new \Exception('Insufficient wallet balance.');
            }

            $buyerWallet->decrement('available_balance', $grossTotal);

            WalletLedger::create([
                'user_id' => $buyer->id,
                'wallet_id' => $buyerWallet->id,
                'type' => 'purchase',
                'direction' => 'debit',
                'amount' => $grossTotal,
                'reference_type' => 'cart_checkout',
                'reference_id' => 0,
                'balance_before' => $buyerBalBefore,
                'balance_after' => $buyerBalBefore - $grossTotal,
                'metadata' => [
                    'checkout_ref' => $checkoutRef,
                    'item_count' => $items->count(),
                ],
            ]);

            // ── 2. For each line: lock product, decrement stock, record sale, credit seller & buyer cashback ──
            $lines = [];
            $saleIds = [];
            $cashbackTotal = 0.0;
            $adminTotal = 0.0;

            foreach ($items as $item) {
                /** @var Product $product */
                $product = Product::where('id', $item->product_id)->lockForUpdate()->first();

                if (! $product || ! $product->is_active || $product->stock < $item->quantity) {
                    throw new \Exception(
                        ($product?->title ?? 'An item').' sold out during checkout. Please try again.'
                    );
                }

                $qty = (int) $item->quantity;
                $unit = round((float) $product->price, 2);
                $split = $this->fees->split($unit * $qty);
                $lineGross = $split['gross'];
                $lineCash = $split['cashback'];
                $lineAdmin = $split['admin_fee'];
                $lineSeller = $split['seller_net'];

                $product->decrement('stock', $qty);
                if ($product->stock <= 0) {
                    $product->update(['is_active' => false]);
                }

                $sale = ProductSale::create([
                    'product_id' => $product->id,
                    'buyer_id' => $buyer->id,
                    'seller_id' => $product->user_id,
                    'quantity' => $qty,
                    'gross_amount' => $lineGross,
                    'admin_fee' => $lineAdmin,
                    'cashback_amount' => $lineCash,
                    'seller_net' => $lineSeller,
                    'status' => 'completed',
                    'reference' => 'SALE-'.strtoupper(Str::random(10)),
                    'checkout_reference' => $checkoutRef,
                ]);
                $saleIds[] = $sale->id;

                // Credit seller
                $sellerWallet = Wallet::firstOrCreate(
                    ['user_id' => $product->user_id],
                    ['currency' => 'ZMW', 'available_balance' => 0, 'pending_balance' => 0]
                );
                $sellerWallet = Wallet::where('id', $sellerWallet->id)->lockForUpdate()->first();

                $sellerBalBefore = (float) $sellerWallet->available_balance;
                $sellerWallet->increment('available_balance', $lineSeller);

                WalletLedger::create([
                    'user_id' => $product->user_id,
                    'wallet_id' => $sellerWallet->id,
                    'type' => 'sale_credit',
                    'direction' => 'credit',
                    'amount' => $lineSeller,
                    'reference_type' => 'product_sale',
                    'reference_id' => $sale->id,
                    'balance_before' => $sellerBalBefore,
                    'balance_after' => $sellerBalBefore + $lineSeller,
                    'metadata' => [
                        'product_title' => $product->title,
                        'quantity' => $qty,
                        'gross_amount' => $lineGross,
                        'admin_fee' => $lineAdmin,
                        'sale_ref' => $sale->reference,
                        'checkout_ref' => $checkoutRef,
                    ],
                ]);

                $cashbackTotal += $lineCash;
                $adminTotal += $lineAdmin;

                $lines[] = [
                    'product_id' => $product->id,
                    'title' => $product->title,
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'line_total' => $lineGross,
                    'cashback_earned' => $lineCash,
                    'sale_reference' => $sale->reference,
                ];
            }

            // ── 3. Credit total cashback (single credit to keep ledger tidy) ──
            if ($cashbackTotal > 0) {
                $buyerWallet->refresh();
                $cashBefore = (float) $buyerWallet->available_balance;
                $buyerWallet->increment('available_balance', $cashbackTotal);
                $buyerWallet->increment('lifetime_cashback_earned', $cashbackTotal);

                WalletLedger::create([
                    'user_id' => $buyer->id,
                    'wallet_id' => $buyerWallet->id,
                    'type' => 'cashback_credit',
                    'direction' => 'credit',
                    'amount' => $cashbackTotal,
                    'reference_type' => 'cart_checkout',
                    'reference_id' => 0,
                    'balance_before' => $cashBefore,
                    'balance_after' => $cashBefore + $cashbackTotal,
                    'metadata' => [
                        'checkout_ref' => $checkoutRef,
                        'cashback_rate' => ProductSaleFeeService::CASHBACK_RATE,
                        'item_count' => $items->count(),
                    ],
                ]);
            }

            // ── 4. Clear buyer's cart ────────────────────────────────────────
            CartItem::where('user_id', $buyer->id)->delete();

            return [
                'reference' => $checkoutRef,
                'sale_ids' => $saleIds,
                'lines' => $lines,
                'totals' => [
                    'gross' => round($grossTotal, 2),
                    'cashback' => round($cashbackTotal, 2),
                    'admin' => round($adminTotal, 2),
                    'net_paid' => round($grossTotal - $cashbackTotal, 2),
                ],
            ];
        });
    }
}
