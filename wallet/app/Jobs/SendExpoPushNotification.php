<?php

namespace App\Jobs;

use App\Models\ExpoPushTicket;
use App\Models\UserPushToken;
use App\Services\ExpoPushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendExpoPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [5, 15, 60, 180, 600];

    /**
     * @param  array{title: string, body: string, data?: array}  $payload
     */
    public function __construct(
        public int $userId,
        public array $payload,
    ) {}

    public function handle(ExpoPushService $expo): void
    {
        if (! (bool) config('services.expo_push.enabled', true)) {
            return;
        }

        $tokens = UserPushToken::query()
            ->active()
            ->where('user_id', $this->userId)
            ->pluck('expo_token')
            ->all();

        if ($tokens === []) {
            return;
        }

        $title = (string) ($this->payload['title'] ?? '');
        $body = (string) ($this->payload['body'] ?? '');
        $data = is_array($this->payload['data'] ?? null) ? ($this->payload['data'] ?? []) : [];

        // Expo recommends batching up to 100 messages per request.
        $tokenChunks = array_chunk($tokens, 100);

        foreach ($tokenChunks as $chunk) {
            $messages = array_map(static fn (string $to) => [
                'to' => $to,
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ], $chunk);

            $result = $expo->send($messages);

            $rows = $result['data']['data'] ?? null;
            if (! is_array($rows)) {
                continue;
            }

            foreach ($rows as $idx => $row) {
                $token = $chunk[$idx] ?? null;
                if (! is_string($token) || $token === '' || ! is_array($row)) {
                    continue;
                }

                $status = (string) ($row['status'] ?? 'error');
                $ticketId = $row['id'] ?? null;
                $details = is_array($row['details'] ?? null) ? ($row['details'] ?? []) : [];
                $error = is_string($details['error'] ?? null) ? $details['error'] : null;

                ExpoPushTicket::query()->create([
                    'user_id' => $this->userId,
                    'expo_token' => $token,
                    'user_notification_id' => is_numeric($data['notification_id'] ?? null) ? (int) $data['notification_id'] : null,
                    'ticket_id' => is_string($ticketId) ? $ticketId : null,
                    'status' => $status === 'ok' ? 'ok' : 'error',
                    'error' => $error,
                    'details' => $details !== [] ? $details : null,
                ]);

                if ($error === 'DeviceNotRegistered') {
                    UserPushToken::query()
                        ->where('expo_token', $token)
                        ->update(['revoked_at' => now()]);
                }
            }
        }

        Log::info('expo.push.sent', [
            'user_id' => $this->userId,
            'tokens' => count($tokens),
        ]);
    }
}

