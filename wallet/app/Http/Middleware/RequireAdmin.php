<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // RBAC: prefer roles/permissions (Spatie). Keep allowlist fallback for safe rollout.
        $isAdmin = false;
        if ($user) {
            $isAdmin = method_exists($user, 'hasAnyRole')
                ? $user->hasAnyRole(['super_admin', 'compliance', 'risk', 'support', 'finance', 'ops', 'merchant_admin'])
                : false;

            if (! $isAdmin && method_exists($user, 'isAdmin')) {
                $isAdmin = $user->isAdmin();
            }
        }

        if (! $user || ! $isAdmin) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Admin access is required for this action.',
                ], 403);
            }

            abort(403, 'Admin access is required for this action.');
        }

        return $next($request);
    }
}
