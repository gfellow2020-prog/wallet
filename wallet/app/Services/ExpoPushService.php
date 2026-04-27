<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushService
{
    protected function client(): PendingRequest
    {
        $timeout = (int) config('services.expo_push.timeout', 10);
        $accessToken = (string) config('services.expo_push.access_token', '');

        $req = Http::baseUrl('https://exp.host/--/api/v2')
            ->acceptJson()
            ->timeout(max(1, $timeout));

        if ($accessToken !== '') {
            $req = $req->withToken($accessToken);
        }

        return $req;
    }

    /**
     * @param  list<array{to:string,title?:string,body?:string,data?:array,sound?:string,priority?:string,channelId?:string}>  $messages
     * @return array{ok: bool, data: array}
     */
    public function send(array $messages): array
    {
        try {
            /** @var Response $response */
            $response = $this->client()->post('/push/send', $messages);
        } catch (\Throwable $e) {
            Log::error('expo.push.error', ['error' => $e->getMessage()]);

            return ['ok' => false, 'data' => []];
        }

        $json = $response->json() ?? [];
        $ok = $response->successful() && (($json['data'] ?? null) !== null);

        if (! $ok) {
            Log::warning('expo.push.failed', [
                'status' => $response->status(),
                'body' => is_array($json) ? array_keys($json) : null,
            ]);
        }

        return ['ok' => $ok, 'data' => is_array($json) ? $json : []];
    }

    /**
     * @param  list<string>  $ticketIds
     * @return array{ok: bool, data: array}
     */
    public function getReceipts(array $ticketIds): array
    {
        try {
            /** @var Response $response */
            $response = $this->client()->post('/push/getReceipts', [
                'ids' => array_values($ticketIds),
            ]);
        } catch (\Throwable $e) {
            Log::error('expo.receipts.error', ['error' => $e->getMessage()]);

            return ['ok' => false, 'data' => []];
        }

        $json = $response->json() ?? [];
        $ok = $response->successful() && (($json['data'] ?? null) !== null);

        if (! $ok) {
            Log::warning('expo.receipts.failed', [
                'status' => $response->status(),
            ]);
        }

        return ['ok' => $ok, 'data' => is_array($json) ? $json : []];
    }
}

