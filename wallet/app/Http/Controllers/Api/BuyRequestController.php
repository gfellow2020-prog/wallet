<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BuyRequest;
use App\Models\Product;
use App\Models\User;
use App\Services\BuyRequestService;
use App\Services\RewardsEngine;
use App\Support\UserDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BuyRequestController extends Controller
{
    public function __construct(
        protected RewardsEngine $rewards,
    ) {}

    /**
     * Default lifetime of a Buy Request token. Long enough for the sponsor to
     * get around to paying, short enough that stale links don't accumulate.
     */
    const DEFAULT_TTL_HOURS = 48;

    /**
     * POST /api/buy-requests
     *
     * Mint a new request. The requester stays the authenticated user; a token
     * is generated server-side and returned (along with the shareable QR
     * payload) so clients never have to trust a client-supplied identifier.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'note' => 'nullable|string|max:280',
            // Optional — when provided, the request is *directed* at a
            // specific sponsor and only they can fulfil it.
            'target_extracash_number' => 'nullable|string|max:20',
        ]);

        $product = Product::find($data['product_id']);
        if (! $product || ! $product->is_active) {
            return response()->json(['message' => 'This product is not available.'], 422);
        }
        if ($product->user_id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot ask someone to buy your own listing.',
            ], 422);
        }

        // Resolve the target user if one was specified.
        $targetUserId = null;
        if (! empty($data['target_extracash_number'])) {
            $normalized = User::normalizeExtracashNumber($data['target_extracash_number']);
            $target = User::where('extracash_number', $normalized)->first();
            if (! $target) {
                return response()->json(['message' => UserDirectory::EXTRA_CASH_LOOKUP_NOT_FOUND], 422);
            }
            if ($target->id === $request->user()->id) {
                return response()->json(['message' => 'You cannot send a request to yourself.'], 422);
            }
            if ($target->id === $product->user_id) {
                return response()->json([
                    'message' => 'You cannot ask the seller to buy their own item for you.',
                ], 422);
            }
            $targetUserId = $target->id;
        }

        $buyRequest = BuyRequest::create([
            'token' => (string) Str::uuid(),
            'product_id' => $product->id,
            'requester_id' => $request->user()->id,
            'target_user_id' => $targetUserId,
            'status' => 'pending',
            'note' => $data['note'] ?? null,
            'expires_at' => now()->addHours(self::DEFAULT_TTL_HOURS),
        ]);

        return response()->json(
            $this->serialize(
                $buyRequest->fresh(['product.seller', 'requester', 'target']),
                $request->user()->id
            ),
            201
        );
    }

    /**
     * GET /api/buy-requests/incoming
     *
     * The "inbox" — pending requests that were *directed at* the current
     * user. Used by the recipient's sponsor UI.
     */
    public function incoming(Request $request): JsonResponse
    {
        $rows = BuyRequest::where('target_user_id', $request->user()->id)
            ->where('status', 'pending')
            ->with(['product.seller:id,name', 'requester:id,name,profile_photo_path,extracash_number'])
            ->latest()
            ->paginate(30);

        // Auto-flip stale rows during the listing pass.
        $rows->getCollection()->transform(function (BuyRequest $r) use ($request) {
            if ($r->status === 'pending' && $r->is_expired) {
                $r->update(['status' => 'expired']);
            }

            return $this->serialize($r->fresh(['product.seller', 'requester', 'target']), $request->user()->id);
        });

        return response()->json($rows);
    }

    /**
     * GET /api/buy-requests/incoming/count
     *
     * Light-weight badge endpoint the Profile screen polls so we can
     * show a pending-count pill without paying to deserialise the full
     * list on every focus.
     */
    public function incomingCount(Request $request): JsonResponse
    {
        $count = BuyRequest::where('target_user_id', $request->user()->id)
            ->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * GET /api/buy-requests/{token}
     *
     * Sponsor-facing preview. Anyone authenticated can read, so the sponsor
     * can see the product + requester and decide whether to pay. We don't
     * leak the requester's private details — just display name.
     */
    public function show(Request $request, string $token): JsonResponse
    {
        $buyRequest = BuyRequest::where('token', $token)
            ->with([
                'product.seller:id,name',
                'requester:id,name,profile_photo_path,extracash_number',
                'target:id,name,extracash_number',
                'fulfiller:id,name',
            ])
            ->first();

        if (! $buyRequest) {
            return response()->json(['message' => 'Request not found.'], 404);
        }

        // Auto-expire stale rows on read so we don't show a "Pay" button that
        // will fail server-side anyway.
        if ($buyRequest->status === 'pending' && $buyRequest->is_expired) {
            $buyRequest->update(['status' => 'expired']);
            $buyRequest->refresh();
        }

        return response()->json($this->serialize($buyRequest, $request->user()->id));
    }

    /**
     * POST /api/buy-requests/{token}/fulfill
     *
     * The sponsor pays. Delegates all money movement to the service which
     * runs everything inside a locked transaction.
     */
    public function fulfill(Request $request, string $token, BuyRequestService $service): JsonResponse
    {
        $buyRequest = BuyRequest::where('token', $token)->first();
        if (! $buyRequest) {
            return response()->json(['message' => 'Request not found.'], 404);
        }

        try {
            $sale = $service->fulfill($buyRequest, $request->user()->id);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        try {
            $this->rewards->recordAction($request->user(), 'buy_product', [
                'source_type' => BuyRequest::class,
                'source_id' => $buyRequest->id,
                'sale_id' => $sale->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        $buyRequest->refresh()->load(['product.seller', 'requester', 'target', 'fulfiller']);

        return response()->json([
            'message' => 'Purchase completed. Thanks for helping out!',
            'buy_request' => $this->serialize($buyRequest, $request->user()->id),
            'sale' => [
                'reference' => $sale->reference,
                'gross_amount' => (float) $sale->gross_amount,
                'cashback_amount' => (float) $sale->cashback_amount,
            ],
        ]);
    }

    /**
     * DELETE /api/buy-requests/{token}
     *
     * Requester cancels. Only the original requester can cancel; the row is
     * kept (status = cancelled) for audit.
     */
    public function destroy(Request $request, string $token): JsonResponse
    {
        $buyRequest = BuyRequest::where('token', $token)->first();
        if (! $buyRequest) {
            return response()->json(['message' => 'Request not found.'], 404);
        }

        if ($buyRequest->requester_id !== $request->user()->id) {
            return response()->json(['message' => 'Only the requester can cancel this request.'], 403);
        }
        if ($buyRequest->status !== 'pending') {
            return response()->json(['message' => 'This request can no longer be cancelled.'], 422);
        }

        $buyRequest->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Request cancelled.']);
    }

    /**
     * GET /api/buy-requests/mine
     *
     * Requester's own outgoing requests. Used by the "My Buy-for-Me Requests"
     * screen to let users track who paid and who is still pending.
     */
    public function mine(Request $request): JsonResponse
    {
        $rows = BuyRequest::where('requester_id', $request->user()->id)
            ->with([
                'product.seller:id,name',
                'target:id,name,extracash_number',
                'fulfiller:id,name',
            ])
            ->latest()
            ->paginate(20);

        // Auto-expire stale pending rows during listing too.
        $rows->getCollection()->transform(function (BuyRequest $r) use ($request) {
            if ($r->status === 'pending' && $r->is_expired) {
                $r->update(['status' => 'expired']);
            }

            return $this->serialize(
                $r->fresh(['product.seller', 'target', 'fulfiller', 'requester']),
                $request->user()->id
            );
        });

        return response()->json($rows);
    }

    /* ───────────────────────── Helpers ───────────────────────── */

    /**
     * Build a consistent JSON shape for mobile clients.
     *
     * We return `can_pay = true` when the authenticated user (passed in
     * explicitly) is allowed to fulfil this request right now, so the
     * sponsor UI can disable/enable the Pay button without re-implementing
     * all the rules.
     */
    private function serialize(BuyRequest $r, ?int $viewerId = null): array
    {
        $product = $r->product;
        $imageUrl = null;
        if ($product && $product->image_url) {
            $imageUrl = str_starts_with($product->image_url, 'http')
                ? $product->image_url
                : url('/storage/'.ltrim($product->image_url, '/'));
        }

        $canPay = false;
        if ($viewerId && $r->isPayable() && $product) {
            $canPay = $viewerId !== (int) $r->requester_id
                   && $viewerId !== (int) $product->user_id
                   && $product->is_active
                   && $product->stock > 0
                   // Directed requests restrict fulfil to the target user.
                   && (empty($r->target_user_id) || $viewerId === (int) $r->target_user_id);
        }

        $requesterPhoto = null;
        if ($r->requester && $r->requester->profile_photo_path) {
            $requesterPhoto = url('/storage/'.ltrim($r->requester->profile_photo_path, '/'));
        }

        return [
            'token' => $r->token,
            'status' => $r->status,
            'note' => $r->note,
            'qr_payload' => $r->qr_payload,
            'expires_at' => $r->expires_at?->toIso8601String(),
            'fulfilled_at' => $r->fulfilled_at?->toIso8601String(),
            'created_at' => $r->created_at?->toIso8601String(),
            'is_expired' => $r->is_expired,
            'can_pay' => $canPay,
            'is_directed' => ! is_null($r->target_user_id),
            'requester' => $r->requester ? [
                'id' => $r->requester->id,
                'name' => $r->requester->name,
                'extracash_number' => $r->requester->extracash_number,
                'profile_photo_url' => $requesterPhoto,
            ] : null,
            'target' => $r->target ? [
                'id' => $r->target->id,
                'name' => $r->target->name,
                'extracash_number' => $r->target->extracash_number,
            ] : null,
            'fulfilled_by' => $r->fulfiller ? [
                'id' => $r->fulfiller->id,
                'name' => $r->fulfiller->name,
            ] : null,
            'product' => $product ? [
                'id' => $product->id,
                'title' => $product->title,
                'price' => (float) $product->price,
                'image_url' => $imageUrl,
                'category' => $product->category,
                'stock' => (int) $product->stock,
                'is_active' => (bool) $product->is_active,
                'seller' => $product->seller ? [
                    'id' => $product->seller->id,
                    'name' => $product->seller->name,
                ] : null,
            ] : null,
        ];
    }
}
