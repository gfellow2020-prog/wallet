<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Models\UserNotificationSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferencesController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $settings = $user->notificationSettings()->first()
            ?? UserNotificationSetting::query()->create([
                'user_id' => $user->id,
                'push_enabled' => true,
                'email_enabled' => false,
                'in_app_enabled' => true,
                'hide_sensitive_push' => true,
            ]);

        $prefs = UserNotificationPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->map(fn (UserNotificationPreference $p) => [
                'type' => $p->type,
                'push_enabled' => $p->push_enabled,
                'email_enabled' => $p->email_enabled,
                'in_app_enabled' => $p->in_app_enabled,
            ])
            ->values();

        return response()->json([
            'settings' => [
                'push_enabled' => $settings->push_enabled,
                'email_enabled' => $settings->email_enabled,
                'in_app_enabled' => $settings->in_app_enabled,
                'hide_sensitive_push' => $settings->hide_sensitive_push,
                'quiet_hours_start' => $settings->quiet_hours_start?->format('H:i'),
                'quiet_hours_end' => $settings->quiet_hours_end?->format('H:i'),
            ],
            'preferences' => $prefs,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'settings' => ['nullable', 'array'],
            'settings.push_enabled' => ['sometimes', 'boolean'],
            'settings.email_enabled' => ['sometimes', 'boolean'],
            'settings.in_app_enabled' => ['sometimes', 'boolean'],
            'settings.hide_sensitive_push' => ['sometimes', 'boolean'],
            'settings.quiet_hours_start' => ['nullable', 'date_format:H:i'],
            'settings.quiet_hours_end' => ['nullable', 'date_format:H:i'],
            'preferences' => ['nullable', 'array'],
            'preferences.*.type' => ['required_with:preferences', 'string', 'max:50'],
            'preferences.*.push_enabled' => ['nullable', 'boolean'],
            'preferences.*.email_enabled' => ['nullable', 'boolean'],
            'preferences.*.in_app_enabled' => ['nullable', 'boolean'],
        ]);

        $settings = $user->notificationSettings()->first()
            ?? UserNotificationSetting::query()->create([
                'user_id' => $user->id,
                'push_enabled' => true,
                'email_enabled' => false,
                'in_app_enabled' => true,
                'hide_sensitive_push' => true,
            ]);

        if (! empty($data['settings']) && is_array($data['settings'])) {
            $s = $data['settings'];
            $settings->update([
                'push_enabled' => $s['push_enabled'] ?? $settings->push_enabled,
                'email_enabled' => $s['email_enabled'] ?? $settings->email_enabled,
                'in_app_enabled' => $s['in_app_enabled'] ?? $settings->in_app_enabled,
                'hide_sensitive_push' => $s['hide_sensitive_push'] ?? $settings->hide_sensitive_push,
                'quiet_hours_start' => $s['quiet_hours_start'] ?? $settings->quiet_hours_start,
                'quiet_hours_end' => $s['quiet_hours_end'] ?? $settings->quiet_hours_end,
            ]);
        }

        if (! empty($data['preferences']) && is_array($data['preferences'])) {
            foreach ($data['preferences'] as $pref) {
                if (! is_array($pref)) {
                    continue;
                }

                UserNotificationPreference::query()->updateOrCreate(
                    ['user_id' => $user->id, 'type' => (string) $pref['type']],
                    [
                        'push_enabled' => array_key_exists('push_enabled', $pref) ? $pref['push_enabled'] : null,
                        'email_enabled' => array_key_exists('email_enabled', $pref) ? $pref['email_enabled'] : null,
                        'in_app_enabled' => array_key_exists('in_app_enabled', $pref) ? $pref['in_app_enabled'] : null,
                    ]
                );
            }
        }

        return $this->show($request);
    }
}

