<?php

namespace App\Services;

use App\Jobs\SendExpoPushNotification;
use App\Mail\GenericNotificationMail;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserNotificationPreference;
use App\Models\UserNotificationSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /**
     * Create an in-app notification and optionally send email + push.
     *
     * @param  array<string, mixed>  $data
     */
    public function notifyUser(
        User $user,
        string $type,
        string $title,
        string $body,
        array $data = [],
        bool $sendEmail = false,
        bool $sendPush = true,
        ?string $dedupeKey = null,
        bool $isSensitive = false,
    ): UserNotification {
        $settings = $user->notificationSettings()->first()
            ?? UserNotificationSetting::query()->create([
                'user_id' => $user->id,
                'push_enabled' => true,
                'email_enabled' => false,
                'in_app_enabled' => true,
                'hide_sensitive_push' => true,
            ]);

        $pref = UserNotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->first();

        $allowInApp = (bool) ($pref?->in_app_enabled ?? $settings->in_app_enabled);
        $allowPush = (bool) ($pref?->push_enabled ?? $settings->push_enabled);
        $allowEmail = (bool) ($pref?->email_enabled ?? $settings->email_enabled);

        if (! $allowInApp && ! $allowPush && ! $allowEmail) {
            // Nothing to do.
            return new UserNotification();
        }

        if ($dedupeKey !== null && $dedupeKey !== '') {
            $row = UserNotification::query()->firstOrCreate([
                'user_id' => $user->id,
                'dedupe_key' => $dedupeKey,
            ], [
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'channel' => 'in_app',
                'is_sensitive' => $isSensitive,
                'is_read' => false,
                'read_at' => null,
                'data' => $data,
            ]);
        } else {
            $row = UserNotification::query()->create([
                'user_id' => $user->id,
                'type' => $type,
                'dedupe_key' => null,
                'title' => $title,
                'body' => $body,
                'channel' => 'in_app',
                'is_sensitive' => $isSensitive,
                'is_read' => false,
                'read_at' => null,
                'data' => $data,
            ]);
        }

        if ($sendEmail && $allowEmail) {
            Mail::to($user->email)->queue(new GenericNotificationMail([
                'title' => $title,
                'body' => $body,
            ]));
        }

        if ($sendPush && $allowPush) {
            $pushBody = $body;
            if ($isSensitive && $settings->hide_sensitive_push) {
                $pushBody = 'Open the app to view details.';
            }

            SendExpoPushNotification::dispatch($user->id, [
                'title' => $title,
                'body' => $pushBody,
                'data' => array_merge(['type' => $type, 'notification_id' => $row->id], $data),
            ])->onQueue('push');
        }

        return $row;
    }

    /**
     * Notify all admin users (config allowlist) for operational alerts.
     *
     * @param  array<string, mixed>  $data
     */
    public function notifyAdmins(string $type, string $title, string $body, array $data = [], bool $sendEmail = true): Collection
    {
        $emails = config('admin.emails', []);
        if (! is_array($emails) || $emails === []) {
            return collect();
        }

        $admins = User::query()
            ->whereIn('email', $emails)
            ->get();

        return $admins->map(fn (User $admin) => $this->notifyUser(
            $admin,
            $type,
            $title,
            $body,
            $data,
            sendEmail: $sendEmail,
            sendPush: false, // admin alerts typically aren’t mobile push
        ));
    }
}

