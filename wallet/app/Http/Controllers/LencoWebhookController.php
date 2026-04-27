<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\LencoService;
use App\Services\NotificationService;

/**
 * Acknowledges Lenco server callbacks (X-Callback-URL and optional dashboard webhooks).
 * Processing can be extended to update local payment/withdrawal status.
 */
class LencoWebhookController extends Controller
{
    public function __construct(
        protected LencoService $lenco,
        protected NotificationService $notifications,
    ) {}

    public function handle(string $action, Request $request): JsonResponse
    {
        $raw = (string) $request->getContent();

        if (! $this->lenco->hasWebhookVerificationConfigured()) {
            return response()->json(['message' => 'Lenco webhook signing secret is not configured.'], 503);
        }

        // Webhook signing is optional in Lenco portal; when enabled, we require a valid signature.
        $signature = $request->header('X-Lenco-Signature')
            ?? $request->header('X-Signature')
            ?? $request->header('X-Webhook-Signature');

        if (! is_string($signature) || $signature === '') {
            $this->notifications->notifyAdmins(
                'admin_webhook_invalid',
                'Lenco webhook rejected',
                'Missing webhook signature header on /webhook/lenco request.',
                ['action' => $action],
                sendEmail: true,
            );
            return response()->json(['message' => 'Missing webhook signature.'], 403);
        }

        if (! $this->lenco->verifyWebhook($raw, $signature)) {
            $this->notifications->notifyAdmins(
                'admin_webhook_invalid',
                'Lenco webhook rejected',
                'Invalid webhook signature on /webhook/lenco request.',
                ['action' => $action],
                sendEmail: true,
            );
            return response()->json(['message' => 'Invalid webhook signature.'], 403);
        }

        $payload = $request->all();
        $reference = $payload['reference'] ?? $payload['transaction_ref'] ?? $payload['data']['reference'] ?? null;
        $status = $payload['status'] ?? $payload['data']['status'] ?? null;

        Log::info('lenco.webhook', [
            'action' => $action,
            'reference' => is_scalar($reference) ? (string) $reference : null,
            'status' => is_scalar($status) ? (string) $status : null,
            'content_length' => strlen($raw),
            'payload_sha256' => hash('sha256', $raw),
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ]);

        return response()->json(['received' => true], 200);
    }
}
