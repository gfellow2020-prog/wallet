<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhoneVerificationRequiredTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_blocked_until_phone_is_verified(): void
    {
        Storage::fake('public');
        Http::fake([
            'www.cloudservicezm.com/*' => Http::response(['ok' => true], 200),
        ]);

        $register = $this->post('/api/register', [
            'name' => 'User',
            'email' => 'u@example.com',
            'phone_number' => '0977000000',
            'password' => 'secret123',
            'nrc_number' => '123456/78/9',
            'tpin' => '1234567890',
            'profile_photo' => UploadedFile::fake()->image('avatar.jpg'),
        ])->assertCreated();

        $this->postJson('/api/login', [
            'email' => 'u@example.com',
            'password' => 'secret123',
        ])->assertStatus(403)
            ->assertJsonPath('phone_verification_required', true);

        $this->postJson('/api/register/otp/verify', [
            'email' => 'u@example.com',
            'otp_id' => $register->json('otp.id'),
            'otp_code' => '123456',
        ])->assertOk()
            ->assertJsonStructure(['token', 'user', 'wallet']);

        // now login allowed (risk-based may apply, but first login is allowed)
        $this->postJson('/api/login', [
            'email' => 'u@example.com',
            'password' => 'secret123',
        ])->assertOk();
    }
}

