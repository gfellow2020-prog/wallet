<?php

namespace Tests\Feature;

use App\Jobs\SendExpoPushNotification;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserPushToken;
use App\Models\UserNotificationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_revoke_expo_push_token(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $resp = $this->postJson('/api/me/push-tokens', [
            'expo_token' => 'ExponentPushToken[abc123]',
            'platform' => 'android',
            'device_id' => 'device-1',
        ]);

        $resp->assertStatus(201);
        $this->assertDatabaseHas('user_push_tokens', [
            'user_id' => $user->id,
            'expo_token' => 'ExponentPushToken[abc123]',
        ]);

        /** @var UserPushToken $token */
        $token = UserPushToken::query()->where('expo_token', 'ExponentPushToken[abc123]')->firstOrFail();

        $this->deleteJson('/api/me/push-tokens/'.$token->id)
            ->assertOk();

        $token->refresh();
        $this->assertNotNull($token->revoked_at);
    }

    public function test_user_can_list_and_mark_notifications_read(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'test',
            'title' => 'Hello',
            'body' => 'World',
            'channel' => 'in_app',
            'is_read' => false,
            'data' => ['x' => 1],
        ]);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $n = UserNotification::query()->where('user_id', $user->id)->firstOrFail();

        $this->postJson('/api/notifications/'.$n->id.'/read')
            ->assertOk();

        $n->refresh();
        $this->assertTrue((bool) $n->is_read);
        $this->assertNotNull($n->read_at);
    }

    public function test_unread_count_endpoint_returns_count(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => 'a',
            'title' => 'A',
            'body' => 'A',
            'channel' => 'in_app',
            'is_read' => false,
        ]);

        $this->getJson('/api/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('unread', 1);
    }

    public function test_privacy_setting_redacts_sensitive_push_body(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        UserNotificationSetting::query()->create([
            'user_id' => $user->id,
            'hide_sensitive_push' => true,
        ]);

        /** @var \App\Services\NotificationService $svc */
        $svc = app(\App\Services\NotificationService::class);

        $svc->notifyUser(
            $user,
            'deposit_pending',
            'Deposit initiated',
            'You initiated a deposit of K100.00.',
            [],
            sendEmail: false,
            sendPush: true,
            dedupeKey: null,
            isSensitive: true,
        );

        Queue::assertPushed(SendExpoPushNotification::class, function (SendExpoPushNotification $job) {
            return ($job->payload['body'] ?? '') === 'Open the app to view details.';
        });
    }

    public function test_notification_service_fans_out_to_push_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        /** @var \App\Services\NotificationService $svc */
        $svc = app(\App\Services\NotificationService::class);

        $svc->notifyUser($user, 'x', 'Title', 'Body', ['k' => 'v'], sendEmail: false, sendPush: true);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => 'x',
            'title' => 'Title',
        ]);

        Queue::assertPushed(SendExpoPushNotification::class);
    }
}

