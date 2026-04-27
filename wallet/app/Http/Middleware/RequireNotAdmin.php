<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireNotAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && method_exists($user, 'hasAnyRole') && $user->hasAnyRole([
            'super_admin',
            'ops',
            'risk',
            'compliance',
            'finance',
            'support',
            'merchant_admin',
        ])) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}

