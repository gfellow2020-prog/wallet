<?php

namespace App\Http\Middleware;

use App\Enums\KycStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireKyc
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $kyc = $user?->kycRecord;

        if (! $kyc || $kyc->status !== KycStatus::Verified) {
            $status = $kyc?->status->value ?? KycStatus::NotSubmitted->value;

            return response()->json([
                'message' => 'KYC verification required before performing this action.',
                'kyc_status' => $status,
            ], 403);
        }

        return $next($request);
    }
}
