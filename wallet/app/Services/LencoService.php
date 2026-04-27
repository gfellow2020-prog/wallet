<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Lenco API Service (v2, base path /access/v2).
 *
 * Wraps Lenco (Zambian fintech) API for:
 * - Collections / Deposits (pull money from bank or mobile money)
 * - Disbursements / Withdrawals (send money to bank or mobile money)
 * - Balance enquiries
 * - Transaction status checks
 *
 * SystemSetting mapping vs Lenco developer portal (API Keys tab):
 * - Lenco v2 authorizes the **API (Secret) key** in `Authorization: Bearer` (see Lenco "Getting started").
 * - `lenco_api_key` → portal "Public Key" (when present) → `X-Secret-Key` together with the bearer token
 * - `lenco_secret_key` → portal "API (Secret) Key" → `Authorization: Bearer` (this is the token that must be valid to avoid 401)
 * - `lenco_environment` (sandbox|live) — must match the key’s environment
 * - `lenco_base_url` → e.g. `https://api.lenco.co/access/v2`
 *
 * Webhook signing secret is configured separately in the portal (Webhook tab) and stored as `lenco_webhook_secret`.
 */
class LencoService
{
    protected string $baseUrl;

    protected string $apiKey;

    protected string $secretKey;

    protected string $webhookSecret;

    protected string $environment;

    protected string $country;

    protected string $lencoAccountId;

    public function __construct()
    {
        $settings = $this->settings();

        $this->baseUrl = $this->normalizeLencoBaseUrl($settings['lenco_base_url'] ?? '');
        $this->apiKey = $settings['lenco_api_key'] ?? '';
        $this->secretKey = $settings['lenco_secret_key'] ?? '';
        $this->webhookSecret = $settings['lenco_webhook_secret'] ?? '';
        $this->environment = $settings['lenco_environment'] ?? 'sandbox';
        $this->country = strtolower($settings['lenco_country'] ?? 'zm');
        $this->lencoAccountId = trim($settings['lenco_account_id'] ?? '');
    }

    /**
     * Fetch Lenco credentials from the system_settings table.
     */
    protected function settings(): array
    {
        return SystemSetting::whereIn('key', [
            'lenco_api_key',
            'lenco_secret_key',
            'lenco_base_url',
            'lenco_webhook_secret',
            'lenco_environment',
            'lenco_country',
            'lenco_account_id',
        ])->pluck('value', 'key')->toArray();
    }

    /**
     * Lenco v2 base URL, always ending in /access/v2 (or equivalent).
     * Stored values often still say /access/v1 — we map those to v2.
     */
    protected function normalizeLencoBaseUrl(string $raw): string
    {
        $url = rtrim($raw, '/');
        if ($url === '') {
            return 'https://api.lenco.co/access/v2';
        }
        if (str_ends_with($url, '/access/v1')) {
            $url = substr($url, 0, -strlen('v1')).'v2';
        }
        if (preg_match('#^https?://api\.lenco\.co$#i', $url)) {
            $url = 'https://api.lenco.co/access/v2';
        }

        return $url;
    }

    /**
     * Is the service properly configured?
     */
    public function configured(): bool
    {
        if (! filled($this->baseUrl)) {
            return false;
        }

        return $this->bearerToken() !== '';
    }

    /* ──────────────────────────────────────────────
     |  HTTP client factory
     |────────────────────────────────────────────── */

    /**
     * Note: request paths must not start with "/"; a leading slash makes Guzzle
     * drop the /access/vX segment and hit the host root (404).
     */
    /**
     * Lenco returns HTTP 401 "Unauthorized" when the Bearer token is not the real API token.
     * The secret from the portal must be used as Bearer; the public key (if any) is sent as X-Secret-Key.
     */
    protected function bearerToken(): string
    {
        $secret = trim($this->secretKey);
        if ($secret !== '') {
            return $secret;
        }

        return trim($this->apiKey);
    }

    protected function client(): PendingRequest
    {
        $bearer = $this->bearerToken();
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$bearer,
            'X-Environment' => $this->environment,
        ];

        $api = trim($this->apiKey);
        $sec = trim($this->secretKey);
        if ($api !== '' && $sec !== '' && $api !== $sec) {
            $headers['X-Secret-Key'] = $api;
        }

        return Http::baseUrl($this->baseUrl)
            ->withHeaders($headers)
            ->timeout(45);
    }

    /* ──────────────────────────────────────────────
     |  Collections (Deposit / Receive money)
     |────────────────────────────────────────────── */

    /**
     * @param  array{
     *   amount: float,
     *   currency: string,
     *   phone_number?: string,
     *   account_number?: string,
     *   account_name?: string,
     *   bank_code?: string,
     *   provider: 'mobile_money'|'bank',
     *   narration?: string,
     *   metadata?: array,
     *   mm_operator?: string,
     * }  $payload
     */
    public function collect(array $payload): array
    {
        $reference = (string) Str::uuid();

        $provider = $payload['provider'] ?? '';

        try {
            if ($provider === 'mobile_money') {
                $op = $this->inferMobileMoneyOperator(
                    $payload['phone_number'] ?? null,
                    $payload['mm_operator'] ?? null
                );
                if (! $op) {
                    return [
                        'success' => false,
                        'data' => [],
                        'message' => 'Could not determine mobile money operator. Use a valid Zambian number or set mm_operator (airtel, mtn, zamtel).',
                        'reference' => $reference,
                    ];
                }
                $phone = $this->normalizeZambiaPhone($payload['phone_number'] ?? '');

                $response = $this->client()
                    ->withHeaders([
                        'X-Transaction-Ref' => $reference,
                        'X-Callback-URL' => $this->callbackUrl('collect'),
                    ])
                    ->post('collections/mobile-money', array_filter([
                        'amount' => (float) $payload['amount'],
                        'reference' => $reference,
                        'phone' => $phone,
                        'operator' => $op,
                        'country' => $this->country,
                        'bearer' => 'merchant',
                    ], static fn ($v) => $v !== null && $v !== ''));

                return $this->parse($response, $reference);
            }

            if ($provider === 'bank') {
                $response = $this->client()
                    ->withHeaders([
                        'X-Transaction-Ref' => $reference,
                        'X-Callback-URL' => $this->callbackUrl('collect'),
                    ])
                    ->post('collections/bank-account', array_filter([
                        'amount' => (float) $payload['amount'],
                        'reference' => $reference,
                        'accountNumber' => $payload['account_number'] ?? null,
                        'bankId' => $payload['bank_code'] ?? null,
                        'country' => $this->country,
                    ], static fn ($v) => $v !== null && $v !== ''));

                return $this->parse($response, $reference);
            }

            return [
                'success' => false,
                'data' => [],
                'message' => 'Invalid collection provider.',
                'reference' => $reference,
            ];
        } catch (\Throwable $e) {
            Log::error('Lenco collect error', ['error' => $e->getMessage(), 'payload' => $payload]);

            return ['success' => false, 'data' => [], 'message' => $e->getMessage(), 'reference' => $reference];
        }
    }

    /**
     * Check collection status.
     */
    public function collectStatus(string $reference): array
    {
        try {
            $ref = rawurlencode($reference);
            $response = $this->client()->get("collections/status/{$ref}");

            return $this->parse($response, $reference);
        } catch (\Throwable $e) {
            Log::error('Lenco collect status error', ['error' => $e->getMessage()]);

            return ['success' => false, 'data' => [], 'message' => $e->getMessage(), 'reference' => $reference];
        }
    }

    /**
     * Alias for Landlord / docs parity — verify collection by reference (GET collections/status).
     */
    public function verifyCollection(string $reference): array
    {
        return $this->collectStatus($reference);
    }

    /**
     * Public callback URL for X-Callback-URL headers (Lenco server-to-server callbacks).
     *
     * @return array<string, string> label => absolute URL
     */
    public function adminListedCallbackUrls(): array
    {
        return [
            'Collections and deposits' => $this->publicCallbackUrl('collect'),
            'Disbursements and withdrawals' => $this->publicCallbackUrl('disburse'),
        ];
    }

    public function publicCallbackUrl(string $action): string
    {
        return $this->callbackUrl($action);
    }

    /* ──────────────────────────────────────────────
     |  Disbursements (Withdraw / Send money)
     |────────────────────────────────────────────── */

    /**
     * @param  array{
     *   amount: float,
     *   currency: string,
     *   phone_number?: string,
     *   account_number?: string,
     *   account_name?: string,
     *   bank_code?: string,
     *   provider: 'mobile_money'|'bank',
     *   narration?: string,
     *   metadata?: array,
     *   mm_operator?: string,
     * }  $payload
     */
    public function disburse(array $payload): array
    {
        $reference = (string) Str::uuid();

        if ($this->lencoAccountId === '') {
            return [
                'success' => false,
                'data' => [],
                'message' => 'Lenco “account ID to debit” is not set. Add it in Admin → Settings (Lenco) as lenco_account_id.',
                'reference' => $reference,
            ];
        }

        $provider = $payload['provider'] ?? '';

        try {
            if ($provider === 'mobile_money') {
                $op = $this->inferMobileMoneyOperator(
                    $payload['phone_number'] ?? null,
                    $payload['mm_operator'] ?? null
                );
                if (! $op) {
                    return [
                        'success' => false,
                        'data' => [],
                        'message' => 'Could not determine mobile money operator. Use a valid Zambian number or set mm_operator (airtel, mtn, zamtel).',
                        'reference' => $reference,
                    ];
                }
                $phone = $this->normalizeZambiaPhone($payload['phone_number'] ?? '');

                $response = $this->client()
                    ->withHeaders([
                        'X-Transaction-Ref' => $reference,
                        'X-Callback-URL' => $this->callbackUrl('disburse'),
                    ])
                    ->post('transfers/mobile-money', array_filter([
                        'accountId' => $this->lencoAccountId,
                        'amount' => (float) $payload['amount'],
                        'reference' => $reference,
                        'narration' => $payload['narration'] ?? 'Wallet withdrawal',
                        'phone' => $phone,
                        'operator' => $op,
                        'country' => $this->country,
                    ], static fn ($v) => $v !== null && $v !== ''));

                return $this->parse($response, $reference);
            }

            if ($provider === 'bank') {
                $response = $this->client()
                    ->withHeaders([
                        'X-Transaction-Ref' => $reference,
                        'X-Callback-URL' => $this->callbackUrl('disburse'),
                    ])
                    ->post('transfers/bank-account', array_filter([
                        'accountId' => $this->lencoAccountId,
                        'amount' => (float) $payload['amount'],
                        'reference' => $reference,
                        'narration' => $payload['narration'] ?? 'Wallet withdrawal',
                        'accountNumber' => $payload['account_number'] ?? null,
                        'bankId' => $payload['bank_code'] ?? null,
                        'country' => $this->country,
                    ], static fn ($v) => $v !== null && $v !== ''));

                return $this->parse($response, $reference);
            }

            return [
                'success' => false,
                'data' => [],
                'message' => 'Invalid disbursement provider.',
                'reference' => $reference,
            ];
        } catch (\Throwable $e) {
            Log::error('Lenco disburse error', ['error' => $e->getMessage(), 'payload' => $payload]);

            return ['success' => false, 'data' => [], 'message' => $e->getMessage(), 'reference' => $reference];
        }
    }

    /**
     * Check disbursement status.
     */
    public function disburseStatus(string $reference): array
    {
        try {
            $ref = rawurlencode($reference);
            $response = $this->client()->get("transfers/status/{$ref}");

            return $this->parse($response, $reference);
        } catch (\Throwable $e) {
            Log::error('Lenco disburse status error', ['error' => $e->getMessage()]);

            return ['success' => false, 'data' => [], 'message' => $e->getMessage(), 'reference' => $reference];
        }
    }

    /* ──────────────────────────────────────────────
     |  Banks
     |────────────────────────────────────────────── */

    /**
     * Get list of supported banks.
     */
    public function banks(): array
    {
        try {
            $response = $this->client()->get('banks', [
                'country' => $this->country,
            ]);

            return $this->parse($response);
        } catch (\Throwable $e) {
            Log::error('Lenco banks error', ['error' => $e->getMessage()]);

            return ['success' => false, 'data' => [], 'message' => $e->getMessage()];
        }
    }

    /**
     * Resolve a bank account (validate account number + bank id).
     */
    public function resolveAccount(string $accountNumber, string $bankCode): array
    {
        try {
            $response = $this->client()->post('resolve/bank-account', [
                'accountNumber' => $accountNumber,
                'bankId' => $bankCode,
            ]);

            return $this->parse($response);
        } catch (\Throwable $e) {
            Log::error('Lenco resolve account error', ['error' => $e->getMessage()]);

            return ['success' => false, 'data' => [], 'message' => $e->getMessage()];
        }
    }

    /* ──────────────────────────────────────────────
     |  Balance & Lookup
     |────────────────────────────────────────────── */

    /**
     * Get wallet / account balance (lists API accounts; returns first as data).
     */
    public function balance(): array
    {
        try {
            $response = $this->client()->get('accounts');

            return $this->parse($response);
        } catch (\Throwable $e) {
            Log::error('Lenco balance error', ['error' => $e->getMessage()]);

            return ['success' => false, 'data' => [], 'message' => $e->getMessage()];
        }
    }

    /**
     * Look up a mobile-money or bank-account name.
     */
    public function nameLookup(string $identifier, string $provider = 'mobile_money', ?string $bankCode = null): array
    {
        try {
            if ($provider === 'bank' && $bankCode !== null) {
                $response = $this->client()->post('resolve/bank-account', [
                    'accountNumber' => $identifier,
                    'bankId' => $bankCode,
                ]);
            } else {
                $op = $this->inferMobileMoneyOperator($identifier, null);
                if (! $op) {
                    return [
                        'success' => false,
                        'data' => [],
                        'message' => 'Could not determine mobile money operator for name lookup.',
                    ];
                }
                $response = $this->client()->post('resolve/mobile-money', [
                    'phone' => $this->normalizeZambiaPhone($identifier),
                    'operator' => $op,
                    'country' => $this->country,
                ]);
            }

            return $this->parse($response);
        } catch (\Throwable $e) {
            Log::error('Lenco name lookup error', ['error' => $e->getMessage()]);

            return ['success' => false, 'data' => [], 'message' => $e->getMessage()];
        }
    }

    /**
     * Verify a webhook signature (HMAC-SHA256).
     */
    public function verifyWebhook(string $payload, string $signature): bool
    {
        if (empty($this->webhookSecret)) {
            return false;
        }

        $computed = hash_hmac('sha256', $payload, $this->webhookSecret);

        return hash_equals($computed, $signature);
    }

    public function hasWebhookVerificationConfigured(): bool
    {
        return trim($this->webhookSecret) !== '';
    }

    /* ──────────────────────────────────────────────
     |  Helpers
     |────────────────────────────────────────────── */

    protected function normalizeZambiaPhone(string $phone): string
    {
        $d = preg_replace('/\D+/', '', $phone) ?? '';
        if ($d === '') {
            return '';
        }
        if (str_starts_with($d, '0')) {
            $d = '260'.substr($d, 1);
        } elseif (strlen($d) === 9) {
            $d = '260'.$d;
        }

        return $d;
    }

    /**
     * @return 'airtel'|'mtn'|'zamtel'|'tnm'|null
     */
    protected function inferMobileMoneyOperator(?string $phone, ?string $explicit = null): ?string
    {
        $e = $explicit ? strtolower(trim($explicit)) : null;
        if ($e) {
            if (in_array($e, ['airtel', 'mtn', 'zamtel'], true)) {
                return $e;
            }
            if ($e === 'tnm' && $this->country === 'mw') {
                return 'tnm';
            }
        }
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (str_starts_with($digits, '260')) {
            $digits = substr($digits, 3);
        }
        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) < 2) {
            return null;
        }
        $prefix2 = (int) substr($digits, 0, 2);
        if (in_array($prefix2, [77, 97], true)) {
            return 'airtel';
        }
        if (in_array($prefix2, [76, 96], true)) {
            return 'mtn';
        }
        if (in_array($prefix2, [75, 95], true)) {
            return 'zamtel';
        }
        if ($this->country === 'mw') {
            if (in_array($prefix2, [99, 88], true)) { // best-effort Malawi
                return $prefix2 === 99 ? 'airtel' : 'tnm';
            }
        }

        return null;
    }

    /**
     * Build the internal callback URL for a given action.
     */
    protected function callbackUrl(string $action): string
    {
        return url("/webhook/lenco/{$action}");
    }

    /**
     * @return array{success: bool, data: array, message: string, reference: string|null}
     */
    protected function parse(Response $response, ?string $reference = null): array
    {
        $body = $response->json() ?? [];

        $raw = $body['status'] ?? null;
        if ($raw === false) {
            $success = false;
        } elseif (! $response->successful()) {
            $success = false;
        } elseif ($raw === true) {
            $success = true;
        } else {
            $success = in_array($raw, ['success', 'pending', 'completed'], true)
                || (array_key_exists('data', $body) && $body['data'] !== null);
        }

        if (! is_array($body)) {
            $body = [];
        }

        Log::info('Lenco response', [
            'status' => $response->status(),
            'success' => $success,
            'reference' => $reference,
        ]);

        $message = $body['message'] ?? ($success ? 'OK' : 'Request failed');
        if (! is_string($message)) {
            $message = 'Request failed';
        }

        if ($response->status() === 401 && stripos($message, 'admin') === false) {
            $message .= ' Check the Lenco API (Secret) key and Sandbox/Live in Admin → Settings.';
        }

        return [
            'success' => $success,
            'data' => $body['data'] ?? $body,
            'message' => $message,
            'reference' => $reference,
        ];
    }
}
