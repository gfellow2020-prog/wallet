<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replays successful JSON responses when the same authenticated user retries
 * the same POST with the same Idempotency-Key and identical body (Stripe-style).
 *
 * - Header: Idempotency-Key (max 255 chars)
 * - Mismatched body for the same key → 409 Conflict
 * - Only caches 2xx responses
 */
class IdempotentMoneyRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $rawKey = trim((string) $request->header('Idempotency-Key', ''));
        if ($rawKey === '' || strlen($rawKey) > 255) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $bodyHash = hash('sha256', $request->getContent());
        $cacheKey = 'idempotent:v1:'.$user->id.':'.$rawKey;
        $lockName = 'lock:idempotent:v1:'.$user->id.':'.$rawKey;

        $lock = Cache::lock($lockName, 30);

        return $lock->block(15, function () use ($request, $next, $cacheKey, $bodyHash) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && isset($cached['status'], $cached['body'], $cached['body_hash'])) {
                if ($cached['body_hash'] !== $bodyHash) {
                    return response()->json([
                        'message' => 'Idempotency-Key was reused with a different request body.',
                    ], 409);
                }

                return response($cached['body'], (int) $cached['status'])
                    ->header('Content-Type', 'application/json')
                    ->header('Idempotency-Replayed', 'true');
            }

            /** @var Response $response */
            $response = $next($request);

            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300) {
                Cache::put($cacheKey, [
                    'status' => $status,
                    'body' => $response->getContent(),
                    'body_hash' => $bodyHash,
                ], now()->addHours(24));
            }

            return $response;
        });
    }
}
