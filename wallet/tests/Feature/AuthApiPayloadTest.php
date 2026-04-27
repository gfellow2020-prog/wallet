<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthApiPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_and_me_responses_do_not_expose_tpin(): void
    {
        Storage::fake('public');
        Http::fake([
            'www.cloudservicezm.com/*' => Http::response(['ok' => true], 200),
        ]);

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->post('/api/register', [
                'name' => 'Major Mac',
                'email' => 'major@example.com',
                'phone_number' => '0977000000',
                'password' => 'secret123',
                'nrc_number' => '123456/78/9',
                'tpin' => '1234567890',
                'profile_photo' => UploadedFile::fake()->image('avatar.jpg'),
            ]);

        $response->assertCreated();
        $this->assertTrue((bool) $response->json('otp_required'));

        // Verify phone OTP to receive token
        $verify = $this->postJson('/api/register/otp/verify', [
            'email' => 'major@example.com',
            'otp_id' => $response->json('otp.id'),
            'otp_code' => '123456',
        ])->assertOk();

        $this->assertArrayNotHasKey('tpin', $verify->json('user'));

        $me = $this->withToken($verify->json('token'))
            ->getJson('/api/me')
            ->assertOk();

        $this->assertArrayNotHasKey('tpin', $me->json('user'));
    }

    public function test_login_response_does_not_expose_tpin(): void
    {
        $user = User::factory()->create([
            'email' => 'major@example.com',
            'password' => 'secret123',
            'nrc_number' => '123456/78/9',
            'tpin' => '1234567890',
            'phone_verified_at' => now(),
        ]);

        Wallet::query()->create([
            'user_id' => $user->id,
            'available_balance' => 0,
            'currency' => 'ZMW',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'major@example.com',
            'password' => 'secret123',
        ]);

        $response->assertOk();
        $this->assertArrayNotHasKey('tpin', $response->json('user'));
    }
}
