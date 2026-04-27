<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * HTTP client for the GeePay mobile-money gateway (ExtraCash product).
 * The API host is the third-party gateway (gateway.mygeepay.com), not "ExtraCash" infrastructure.
 */
class ExtraCashGatewayService
{
    protected string $baseUrl;

    protected string $clientId;

    protected string $authSignature;

    protected string $bearerToken;

    protected string $callbackUrl;

    protected string $webhookSecret;

    protected string $webhookHeader;

    public function __construct()
    {
        $config = config('services.extracash_gateway');

        $this->baseUrl = rtrim($config['base_url'] ?? '', '/');
        $this->clientId = $config['client_id'] ?? '';
        $this->authSignature = $config['auth_signature'] ?? '';
        $this->bearerToken = $config['bearer_token'] ?? '';
        $this->callbackUrl = $config['callback_url'] ?? '';
        $this->webhookSecret = $config['webhook_secret'] ?? '';
        $this->webhookHeader = $config['webhook_header'] ?? 'X-Geepay-Webhook-Secret';
    }

    /* ──────────────────────────────────────────────
     |  HTTP client factory
     |────────────────────────────────────────────── */

    protected function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-Client-ID' => $this->clientId,
                'X-Auth-Signature' => $this->authSignature,
            ])
            ->withToken($this->bearerToken)
            ->timeout(30);
    }

    /* ──────────────────────────────────────────────
     |  Mobile-Money: Disburse (Send money out)
     |────────────────────────────────────────────── */

    /**
     * @return array{success: bool, data: array, message: string, reference: string|null}
     */
    public function disburse(string $phoneNumber, float $amount, string $narration = 'Wallet disbursement'): array
    {
        $transactionRef = (string) Str::uuid();

        try {
            $response = $this->client()
                ->withHeaders([
                    'X-Transaction-Ref' => $transactionRef,
                    'X-Callback-URL' => $this->callbackUrl,
                ])
                ->post('/mobile-money/disburse', [
                    'phone_number' => $phoneNumber,
                    'amount' => $amount,
                    'narration' => $narration,
                ]);

            return $this->parse($response, $transactionRef);
        } catch (\Throwable $e) {
            Log::error('ExtraCash gateway disburse error', ['error' => $e->getMessage()]);

            return ['success' => false, 'data' => [], 'message' => $e->getMessage(), 'reference' => $transactionRef];
        }
    }

    public function disburseStatus(string $reference): array
    {
        try {
            $response = $this->client()->get("/mobile-money/disburse/status/{$reference}");

            return $this->parse($response, $reference);
        } catch (\Throwable $e) {
            Log::error('ExtraCash gateway disburse status error', ['error' => $e->getMessage()]);

            return ['success' => false, 'data' => [], 'message' => $e->getMessage(), 'reference' => $reference];
        }
    }

    public function disburseBalance(): array
    {
        try {
            $response = $this->client()->get('/mobile-money/disburse/balance');

            return $this->parse($response);
        } catch (\Throwable $e) {
            Log::error('ExtraCash gateway disburse balance error', ['error' => $e->getMessage()]);

            return ['success' => false, 'data' => [], 'message' => $e->getMessage()];
        }
    }

    /* ──────────────────────────────────────────────
     |  Mobile-Money: Collect (Receive money in)
     |────────────────────────────────────────────── */

    /**
     * @return array{success: bool, data: array, message: string, reference: string|null}
     */
    public function collect(string $phoneNumber, float $amount): array
    {
        $transactionRef = (string) Str::uuid();

        try {
            $response = $this->client()
                ->withHeaders([
                    'X-Transaction-Ref' => $transactionRef,
                    'X-Callback-URL' => $this->callbackUrl,
                ])
                ->post('/mobile-money/collect', [
                    'phone_number' => $phoneNumber,
                    'amount' => $amount,
                ]);

            return $this->parse($response, $transactionRef);
        } catch (\Throwable $e) {
            Log::error('ExtraCash gateway collect error', ['error' => $e->getMessage()]);

            return ['success' => false, 'data' => [], 'message' => $e->getMessage(), 'reference' => $transactionRef];
        }
    }

    public function collectStatus(string $reference): array
    {
        try {
            $response = $this->client()->get("/mobile-money/check-status/{$reference}");

            return $this->parse($response, $reference);
        } catch (\Throwable $e) {
            Log::error('ExtraCash gateway collect status error', ['error' => $e->getMessage()]);

            return ['success' => false, 'data' => [], 'message' => $e->getMessage(), 'reference' => $reference];
        }
    }

    public function collectBalance(): array
    {
        try {
            $response = $this->client()->get('/mobile-money/collect/balance');

            return $this->parse($response);
        } catch (\Throwable $e) {
            Log::error('ExtraCash gateway collect balance error', ['error' => $e->getMessage()]);

            return ['success' => false, 'data' => [], 'message' => $e->getMessage()];
        }
    }

    public function nameLookup(string $phoneNumber): array
    {
        try {
            $response = $this->client()->get("/mobile-money/name-lookup/{$phoneNumber}");

            return $this->parse($response);
        } catch (\Throwable $e) {
            Log::error('ExtraCash gateway name lookup error', ['error' => $e->getMessage()]);

            return ['success' => false, 'data' => [], 'message' => $e->getMessage()];
        }
    }

    protected function parse(Response $response, ?string $reference = null): array
    {
        $body = $response->json() ?? [];

        $success = $response->successful() && ($body['status'] ?? null) !== 'error';

        $message = $body['message'] ?? ($success ? 'OK' : 'Request failed');
        if (! is_string($message)) {
            $message = $success ? 'OK' : 'Request failed';
        }

        // Never log full gateway response bodies (may contain PII or sensitive identifiers).
        Log::info('ExtraCash gateway response', [
            'status' => $response->status(),
            'success' => $success,
            'reference' => $reference,
            'gateway_status' => is_scalar($body['status'] ?? null) ? (string) ($body['status'] ?? '') : null,
            'message' => $success ? null : $message,
        ]);

        return [
            'success' => $success,
            'data' => $body['data'] ?? $body,
            'message' => $message,
            'reference' => $reference,
        ];
    }

    public function webhookHeader(): string
    {
        return $this->webhookHeader;
    }

    public function hasWebhookVerificationConfigured(): bool
    {
        return $this->webhookSecret !== '';
    }

    public function verifyWebhookHeader(?string $providedSecret): bool
    {
        if ($this->webhookSecret === '' || ! is_string($providedSecret) || $providedSecret === '') {
            return false;
        }

        return hash_equals($this->webhookSecret, $providedSecret);
    }
}
