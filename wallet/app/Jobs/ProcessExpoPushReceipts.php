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

class ProcessExpoPushReceipts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [10, 30, 120, 300, 900];

    public function handle(ExpoPushService $expo): void
    {
        if (! (bool) config('services.expo_push.enabled', true)) {
            return;
        }

        $tickets = ExpoPushTicket::query()
            ->whereNotNull('ticket_id')
            ->whereNull('checked_at')
            ->orderBy('id')
            ->limit(200)
            ->get();

        if ($tickets->isEmpty()) {
            return;
        }

        $ids = $tickets->pluck('ticket_id')->filter()->values()->all();
        $chunks = array_chunk($ids, 300); // Expo allows multiple ids; keep bounded.

        foreach ($chunks as $chunk) {
            $result = $expo->getReceipts($chunk);
            $data = $result['data']['data'] ?? null;
            if (! is_array($data)) {
                continue;
            }

            foreach ($data as $ticketId => $receipt) {
                if (! is_array($receipt)) {
                    continue;
                }

                $status = (string) ($receipt['status'] ?? 'error');
                $details = is_array($receipt['details'] ?? null) ? ($receipt['details'] ?? []) : [];
                $error = is_string($details['error'] ?? null) ? $details['error'] : null;

                $q = ExpoPushTicket::query()->where('ticket_id', $ticketId);
                $ticket = $q->first();
                if (! $ticket) {
                    continue;
                }

                $ticket->update([
                    'checked_at' => now(),
                    'status' => $status === 'ok' ? 'ok' : 'error',
                    'error' => $error,
                    'details' => $details !== [] ? $details : ($ticket->details ?? null),
                ]);

                if ($error === 'DeviceNotRegistered') {
                    UserPushToken::query()
                        ->where('expo_token', $ticket->expo_token)
                        ->update(['revoked_at' => now()]);
                }
            }
        }

        Log::info('expo.receipts.processed', ['count' => $tickets->count()]);
    }
}

