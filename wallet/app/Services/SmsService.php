<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private function settings(): array
    {
        $settings = Cache::remember('system_settings', 300, function () {
            return SystemSetting::pluck('value', 'key')->toArray();
        });

        return [
            'enabled' => (string) ($settings['sms_enabled'] ?? '0') === '1',
            'username' => (string) ($settings['sms_username'] ?? ''),
            'password' => (string) ($settings['sms_password'] ?? ''),
            'sender_id' => (string) ($settings['sms_sender_id'] ?? ''),
            'short_code' => (string) ($settings['sms_short_code'] ?? ''),
        ];
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl('https://www.cloudservicezm.com')
            ->acceptJson()
            ->asJson()
            ->timeout(15);
    }

    /**
     * @return array{ok: bool, message: string, provider: array}
     */
    public function send(string $phone, string $message): array
    {
        $s = $this->settings();

        if (! $s['enabled']) {
            return ['ok' => false, 'message' => 'SMS is disabled.', 'provider' => []];
        }

        if ($s['username'] === '' || $s['password'] === '' || $s['sender_id'] === '' || $s['short_code'] === '') {
            return ['ok' => false, 'message' => 'SMS is not configured.', 'provider' => []];
        }

        try {
            $response = $this->client()->post('/smsservice/jsonapi', [
                'auth' => [
                    'username' => $s['username'],
                    'password' => $s['password'],
                    'sender_id' => $s['sender_id'],
                    'short_code' => $s['short_code'],
                ],
                'messages' => [
                    [
                        'phone' => $phone,
                        'message' => $message,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('sms.send.error', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'SMS send failed.', 'provider' => []];
        }

        $json = $response->json() ?? [];
        $ok = $response->successful();

        if (! $ok) {
            Log::warning('sms.send.failed', [
                'status' => $response->status(),
                'phone_last4' => substr(preg_replace('/\\D+/', '', $phone) ?? '', -4),
                'keys' => is_array($json) ? array_keys($json) : null,
            ]);
        }

        return [
            'ok' => $ok,
            'message' => $ok ? 'Sent.' : 'Provider rejected request.',
            'provider' => is_array($json) ? $json : [],
        ];
    }
}

