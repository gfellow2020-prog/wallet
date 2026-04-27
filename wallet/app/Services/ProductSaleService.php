<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductSale;
use App\Models\Wallet;
use App\Models\WalletLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSaleService
{
    public function __construct(
        protected ProductSaleFeeService $fees,
    ) {}

    /**
     * Process a product purchase.
     *
     * @param  Product  $product  The product being purchased
     * @param  int  $buyerId  The ID of the buyer
     *
     * @throws \Exception on insufficient stock, self-purchase, or wallet issues
     */
    public function purchase(Product $product, int $buyerId): ProductSale
    {
        if ($product->user_id === $buyerId) {
            throw new \Exception('You cannot buy your own listing.');
        }

        if ($product->stock < 1 || ! $product->is_active) {
            throw new \Exception('This product is no longer available.');
        }

        $split = $this->fees->split((float) $product->price);
        $gross = $split['gross'];
        $cashbackAmt = $split['cashback'];
        $adminFee = $split['admin_fee'];
        $sellerNet = $split['seller_net'];

        // Pre-check balance so we can return a friendly error.
        $buyerWalletCheck = Wallet::firstOrCreate(
            ['user_id' => $buyerId],
            ['currency' => 'ZMW', 'available_balance' => 0, 'pending_balance' => 0]
        );
        if ((float) $buyerWalletCheck->available_balance < $gross) {
            throw new \Exception('Insufficient wallet balance. Please top up and try again.');
        }

        return DB::transaction(function () use ($product, $buyerId, $gross, $cashbackAmt, $adminFee, $sellerNet) {
            // ── 1. Decrement product stock ───────────────────────────────
            $product->decrement('stock');
            if ($product->stock === 0) {
                $product->update(['is_active' => false]);
            }

            // ── 2. Record the sale ───────────────────────────────────────
            $sale = ProductSale::create([
                'product_id' => $product->id,
                'buyer_id' => $buyerId,
                'seller_id' => $product->user_id,
                'quantity' => 1,
                'gross_amount' => $gross,
                'admin_fee' => $adminFee,
                'cashback_amount' => $cashbackAmt,
                'seller_net' => $sellerNet,
                'status' => 'completed',
                'reference' => 'SALE-'.strtoupper(Str::random(10)),
            ]);

            // ── 3. Debit buyer for the gross amount ─────────────────────
            $buyerWallet = Wallet::where('user_id', $buyerId)->lockForUpdate()->first();

            $debitBefore = (float) $buyerWallet->available_balance;
            if ($debitBefore < $gross) {
                throw new \Exception('Insufficient wallet balance.');
            }
            $buyerWallet->decrement('available_balance', $gross);

            WalletLedger::create([
                'user_id' => $buyerId,
                'wallet_id' => $buyerWallet->id,
                'type' => 'purchase',
                'direction' => 'debit',
                'amount' => $gross,
                'reference_type' => 'product_sale',
                'reference_id' => $sale->id,
                'balance_before' => $debitBefore,
                'balance_after' => $debitBefore - $gross,
                'metadata' => json_encode([
                    'product_title' => $product->title,
                    'sale_ref' => $sale->reference,
                ]),
            ]);

            // ── 4. Credit 2% cashback to buyer's wallet ─────────────────
            $buyerWallet->refresh();
            $balBefore = (float) $buyerWallet->available_balance;
            $buyerWallet->increment('available_balance', $cashbackAmt);
            $buyerWallet->increment('lifetime_cashback_earned', $cashbackAmt);

            WalletLedger::create([
                'user_id' => $buyerId,
                'wallet_id' => $buyerWallet->id,
                'type' => 'cashback_credit',
                'direction' => 'credit',
                'amount' => $cashbackAmt,
                'reference_type' => 'product_sale',
                'reference_id' => $sale->id,
                'balance_before' => $balBefore,
                'balance_after' => $balBefore + $cashbackAmt,
                'metadata' => json_encode([
                    'product_title' => $product->title,
                    'cashback_rate' => ProductSaleFeeService::CASHBACK_RATE,
                    'sale_ref' => $sale->reference,
                ]),
            ]);

            // ── 5. Credit 97% net to seller's wallet ─────────────────────
            $sellerWallet = Wallet::firstOrCreate(
                ['user_id' => $product->user_id],
                ['currency' => 'ZMW', 'available_balance' => 0, 'pending_balance' => 0]
            );
            $sellerWallet = Wallet::where('id', $sellerWallet->id)->lockForUpdate()->first();

            $sellerBalBefore = (float) $sellerWallet->available_balance;
            $sellerWallet->increment('available_balance', $sellerNet);

            WalletLedger::create([
                'user_id' => $product->user_id,
                'wallet_id' => $sellerWallet->id,
                'type' => 'sale_credit',
                'direction' => 'credit',
                'amount' => $sellerNet,
                'reference_type' => 'product_sale',
                'reference_id' => $sale->id,
                'balance_before' => $sellerBalBefore,
                'balance_after' => $sellerBalBefore + $sellerNet,
                'metadata' => json_encode([
                    'product_title' => $product->title,
                    'gross_amount' => $gross,
                    'admin_fee' => $adminFee,
                    'sale_ref' => $sale->reference,
                ]),
            ]);

            return $sale;
        });
    }
}
