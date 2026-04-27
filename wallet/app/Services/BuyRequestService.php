<?php

namespace App\Services;

use App\Models\BuyRequest;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\Wallet;
use App\Models\WalletLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Buy for Me" fulfillment service.
 *
 * A Buy Request is a single-use, expiring token a requester mints and shares.
 * Any authenticated sponsor (who isn't the requester or the seller) can fulfil
 * it from their own wallet. Monetary rules mirror the regular single-product
 * purchase exactly (see ProductSaleService):
 *
 *   Sponsor pays 100% of the listed price.
 *   Seller receives 97% (net).
 *   Sponsor receives 2% cashback (loyalty stays with the paying wallet).
 *   Platform retains 1% admin fee.
 *
 * The requester is the "recipient" recorded on the BuyRequest row. The
 * product_sale is the canonical accounting record (buyer_id = sponsor), and
 * buy_requests.product_sale_id links the two so either party can reconcile.
 *
 * All state changes happen inside a single DB::transaction with locked
 * rows, so concurrent taps / double-scans can never over-spend a wallet
 * or over-sell a product.
 */
class BuyRequestService
{
    public function __construct(
        protected ProductSaleFeeService $fees,
    ) {}

    /**
     * Pay a pending BuyRequest from the sponsor's wallet.
     *
     * @throws \Exception on any guard-rail failure. Caller should translate
     *                    to a friendly 4xx JSON response.
     */
    public function fulfill(BuyRequest $request, int $sponsorId): ProductSale
    {
        return DB::transaction(function () use ($request, $sponsorId) {
            // ── 1. Re-load with locks so we have a consistent view ───────
            /** @var BuyRequest $req */
            $req = BuyRequest::whereKey($request->id)->lockForUpdate()->first();
            if (! $req) {
                throw new \Exception('This request no longer exists.');
            }

            if ($req->status !== 'pending') {
                throw new \Exception('This request is no longer available.');
            }
            if ($req->expires_at && $req->expires_at->isPast()) {
                // Flip to expired so subsequent reads don't lie.
                $req->update(['status' => 'expired']);
                throw new \Exception('This request has expired.');
            }

            /** @var Product $product */
            $product = Product::whereKey($req->product_id)->lockForUpdate()->first();
            if (! $product || ! $product->is_active || $product->stock < 1) {
                throw new \Exception('This product is no longer available.');
            }

            // ── 2. Guard sponsor identity ────────────────────────────────
            if ($sponsorId === (int) $req->requester_id) {
                throw new \Exception('You cannot fulfil your own request.');
            }
            if ($sponsorId === (int) $product->user_id) {
                throw new \Exception('Sellers cannot fulfil requests for their own listings.');
            }
            // If the requester targeted a specific user, only that user
            // is allowed to fulfil — open-link sharing is bypassed.
            if ($req->target_user_id && (int) $req->target_user_id !== $sponsorId) {
                throw new \Exception('This request was sent to someone else.');
            }

            // ── 3. Compute split ─────────────────────────────────────────
            $split = $this->fees->split((float) $product->price);
            $gross = $split['gross'];
            $cashback = $split['cashback'];
            $adminFee = $split['admin_fee'];
            $sellerNet = $split['seller_net'];

            // ── 4. Lock sponsor wallet and verify balance ────────────────
            $sponsorWallet = Wallet::firstOrCreate(
                ['user_id' => $sponsorId],
                ['currency' => 'ZMW', 'available_balance' => 0, 'pending_balance' => 0]
            );
            $sponsorWallet = Wallet::whereKey($sponsorWallet->id)->lockForUpdate()->first();

            $sponsorBefore = (float) $sponsorWallet->available_balance;
            if ($sponsorBefore < $gross) {
                throw new \Exception('Insufficient wallet balance. Please top up and try again.');
            }

            // ── 5. Decrement stock ──────────────────────────────────────
            $product->decrement('stock');
            if ($product->stock === 0) {
                $product->update(['is_active' => false]);
            }

            // ── 6. Create the sale row (buyer = sponsor, for accounting) ─
            $sale = ProductSale::create([
                'product_id' => $product->id,
                'buyer_id' => $sponsorId,
                'seller_id' => $product->user_id,
                'quantity' => 1,
                'gross_amount' => $gross,
                'admin_fee' => $adminFee,
                'cashback_amount' => $cashback,
                'seller_net' => $sellerNet,
                'status' => 'completed',
                'reference' => 'GIFT-'.strtoupper(Str::random(10)),
            ]);

            // ── 7. Debit sponsor ────────────────────────────────────────
            $sponsorWallet->decrement('available_balance', $gross);
            WalletLedger::create([
                'user_id' => $sponsorId,
                'wallet_id' => $sponsorWallet->id,
                'type' => 'purchase',
                'direction' => 'debit',
                'amount' => $gross,
                'reference_type' => 'product_sale',
                'reference_id' => $sale->id,
                'balance_before' => $sponsorBefore,
                'balance_after' => $sponsorBefore - $gross,
                'metadata' => json_encode([
                    'product_title' => $product->title,
                    'sale_ref' => $sale->reference,
                    'buy_for_user' => $req->requester_id,
                    'buy_request' => $req->token,
                ]),
            ]);

            // ── 8. Credit cashback to sponsor ───────────────────────────
            $sponsorWallet->refresh();
            $cashbackBefore = (float) $sponsorWallet->available_balance;
            $sponsorWallet->increment('available_balance', $cashback);
            $sponsorWallet->increment('lifetime_cashback_earned', $cashback);

            WalletLedger::create([
                'user_id' => $sponsorId,
                'wallet_id' => $sponsorWallet->id,
                'type' => 'cashback_credit',
                'direction' => 'credit',
                'amount' => $cashback,
                'reference_type' => 'product_sale',
                'reference_id' => $sale->id,
                'balance_before' => $cashbackBefore,
                'balance_after' => $cashbackBefore + $cashback,
                'metadata' => json_encode([
                    'product_title' => $product->title,
                    'cashback_rate' => ProductSaleFeeService::CASHBACK_RATE,
                    'sale_ref' => $sale->reference,
                    'buy_request' => $req->token,
                ]),
            ]);

            // ── 9. Credit seller ────────────────────────────────────────
            $sellerWallet = Wallet::firstOrCreate(
                ['user_id' => $product->user_id],
                ['currency' => 'ZMW', 'available_balance' => 0, 'pending_balance' => 0]
            );
            $sellerWallet = Wallet::whereKey($sellerWallet->id)->lockForUpdate()->first();

            $sellerBefore = (float) $sellerWallet->available_balance;
            $sellerWallet->increment('available_balance', $sellerNet);

            WalletLedger::create([
                'user_id' => $product->user_id,
                'wallet_id' => $sellerWallet->id,
                'type' => 'sale_credit',
                'direction' => 'credit',
                'amount' => $sellerNet,
                'reference_type' => 'product_sale',
                'reference_id' => $sale->id,
                'balance_before' => $sellerBefore,
                'balance_after' => $sellerBefore + $sellerNet,
                'metadata' => json_encode([
                    'product_title' => $product->title,
                    'gross_amount' => $gross,
                    'admin_fee' => $adminFee,
                    'sale_ref' => $sale->reference,
                    'buy_request' => $req->token,
                ]),
            ]);

            // ── 10. Mark the request fulfilled ──────────────────────────
            $req->update([
                'status' => 'fulfilled',
                'fulfilled_by' => $sponsorId,
                'fulfilled_at' => now(),
                'product_sale_id' => $sale->id,
            ]);

            return $sale;
        });
    }
}
