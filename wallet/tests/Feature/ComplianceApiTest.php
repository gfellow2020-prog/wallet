<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ComplianceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('system_settings');
        config([
            'services.smartdata.api_key' => 'test-smartdata-key',
            'services.smartdata.base_url' => 'https://mysmartdata.tech/api/v1',
        ]);
    }

    public function test_nrc_verify_extracts_tpin_from_nested_provider_data(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'reference' => 'req_nested',
                'data' => [
                    'full_name' => 'Nested User',
                    'details' => ['TPIN' => '5566778899'],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/nrc/verify', [
            'nrc_number' => '123456/78/9',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Nested User')
            ->assertJsonPath('data.tpin', '5566778899');
    }

    public function test_nrc_verify_uses_system_settings_for_smartdata_base_url_and_api_key(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'smartdata_api_key'],
            ['value' => 'key-from-admin-settings']
        );
        SystemSetting::query()->updateOrCreate(
            ['key' => 'smartdata_base_url'],
            ['value' => 'https://compliance.example.com/api/v1']
        );
        Cache::flush();

        Http::fake([
            'https://compliance.example.com/api/v1/nrc/verify' => Http::response([
                'success' => true,
                'reference' => 'req_from_db',
                'id_number' => '123456/78/9',
                'data' => [
                    'full_name' => 'Test User',
                    'tpin' => '1111222233',
                ],
                'message' => 'OK',
            ], 200),
        ]);

        $response = $this->postJson('/api/nrc/verify', [
            'nrc_number' => '123456/78/9',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.full_name', 'Test User')
            ->assertJsonPath('data.tpin', '1111222233');

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://compliance.example.com/api/v1/nrc/verify'
                && ($request->header('X-API-Key')[0] ?? null) === 'key-from-admin-settings'
                && $request['nrc_number'] === '123456/78/9';
        });
    }

    public function test_nrc_verify_returns_mapped_payload_from_provider(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'reference' => 'req_123',
                'id_number' => '123456/78/9',
                'data' => [
                    'full_name' => 'Major Mac',
                    'first_name' => 'Major',
                    'last_name' => 'Mac',
                    'tpin' => '1234567890',
                ],
                'message' => 'NRC verified',
            ], 200),
        ]);

        $response = $this->postJson('/api/nrc/verify', [
            'nrc_number' => '123456/78/9',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.full_name', 'Major Mac')
            ->assertJsonPath('data.tpin', '1234567890');
    }

    public function test_nrc_verify_returns_502_when_provider_fails(): void
    {
        Http::fake(function () {
            throw new \RuntimeException('Provider unavailable');
        });

        $response = $this->postJson('/api/nrc/verify', [
            'nrc_number' => '123456/78/9',
        ]);

        $response
            ->assertStatus(502)
            ->assertJsonPath('success', false);
    }

    public function test_nrc_verify_returns_422_when_api_key_is_not_configured(): void
    {
        Cache::forget('system_settings');
        config(['services.smartdata.api_key' => null]);

        $response = $this->postJson('/api/nrc/verify', [
            'nrc_number' => '123456/78/9',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'NRC verification API key is not configured.');
    }

    public function test_nrc_verify_is_rate_limited(): void
    {
        Http::fake([
            '*' => Http::response([
                'success' => true,
                'reference' => 'req_123',
                'data' => ['full_name' => 'Major Mac', 'tpin' => '1234567890'],
            ], 200),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
                ->postJson('/api/nrc/verify', ['nrc_number' => '123456/78/9'])
                ->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->postJson('/api/nrc/verify', ['nrc_number' => '123456/78/9'])
            ->assertStatus(429);
    }

    public function test_authenticated_user_can_upload_profile_photo(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'nrc_number' => '123456/78/9',
            'tpin' => '1234567890',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/me/profile-photo', [
            'profile_photo' => UploadedFile::fake()->image('avatar.jpg', 300, 300),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Profile photo updated successfully.')
            ->assertJsonPath('user.id', $user->id);

        $user->refresh();
        $this->assertNotNull($user->profile_photo_path);
        $this->assertStringStartsWith('profile-photos/', $user->profile_photo_path);
        Storage::disk('public')->assertExists($user->profile_photo_path);
    }
}
