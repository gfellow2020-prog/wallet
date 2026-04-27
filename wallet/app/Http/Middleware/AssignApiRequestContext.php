<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assigns a stable request id for API correlation (logs + client support).
 *
 * Accepts X-Request-Id when it matches a safe pattern; otherwise generates a UUID.
 * Echoes the id on X-Request-Id on every API response.
 */
class AssignApiRequestContext
{
    private const MAX_CLIENT_REQUEST_ID_LEN = 64;

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);
        $request->attributes->set('request_id', $requestId);

        Log::withContext([
            'request_id' => $requestId,
            'http.method' => $request->method(),
            'http.path' => '/'.$request->path(),
        ]);

        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    private function resolveRequestId(Request $request): string
    {
        $raw = $request->headers->get('X-Request-Id');
        if (! is_string($raw)) {
            return (string) Str::uuid();
        }

        $raw = trim($raw);
        if ($raw === '' || strlen($raw) > self::MAX_CLIENT_REQUEST_ID_LEN) {
            return (string) Str::uuid();
        }

        if (preg_match('/^[a-zA-Z0-9._-]+$/', $raw) !== 1) {
            return (string) Str::uuid();
        }

        return $raw;
    }
}
