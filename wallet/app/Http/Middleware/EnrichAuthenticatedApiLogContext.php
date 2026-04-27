<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the authenticated user id to log context (runs after auth:sanctum).
 */
class EnrichAuthenticatedApiLogContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user !== null) {
            Log::withContext(['user_id' => $user->id]);
        }

        return $next($request);
    }
}
