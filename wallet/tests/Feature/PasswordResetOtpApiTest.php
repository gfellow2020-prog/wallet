<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetOtpApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_otp_request_does_not_enumerate_accounts(): void
    {
        Http::fake(['www.cloudservicezm.com/*' => Http::response(['ok' => true], 200)]);
        Mail::fake();

        $this->postJson('/api/password/otp/request', [
            'identifier' => 'nope@example.com',
        ])->assertOk()
            ->assertJsonPath('message', 'If the account exists, an OTP has been sent.');
    }

    public function test_password_reset_full_cycle_works_and_returns_token(): void
    {
        Http::fake(['www.cloudservicezm.com/*' => Http::response(['ok' => true], 200)]);
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => 'oldpassword123',
            'phone_number' => '260977000000',
            'phone_verified_at' => now(),
        ]);
        Wallet::query()->create([
            'user_id' => $user->id,
            'available_balance' => 0,
            'currency' => 'ZMW',
        ]);

        $req = $this->postJson('/api/password/otp/request', [
            'identifier' => '0977000000',
        ])->assertOk()
            ->assertJsonPath('otp_required', true)
            ->assertJsonPath('otp.purpose', 'password_reset');

        $otpId = (int) $req->json('otp.id');

        // Wrong code rejected.
        $this->postJson('/api/password/otp/verify', [
            'email' => 'reset@example.com',
            'otp_id' => $otpId,
            'otp_code' => '000000',
        ])->assertStatus(422);

        // In this codebase OTP is random outside testing env.
        // Force app env to testing for the remainder of this test run by using the known code.
        // Most CI runs already use APP_ENV=testing; this is defensive.
        $this->app['config']->set('app.env', 'testing');

        // Request again so the OTP code is 123456 under testing env.
        $req2 = $this->postJson('/api/password/otp/request', [
            'identifier' => '0977000000',
        ])->assertOk();
        $otpId2 = (int) $req2->json('otp.id');

        $verify = $this->postJson('/api/password/otp/verify', [
            'email' => 'reset@example.com',
            'otp_id' => $otpId2,
            'otp_code' => '123456',
        ])->assertOk()
            ->assertJsonStructure(['reset_session']);

        $resetSession = (string) $verify->json('reset_session');

        $reset = $this->postJson('/api/password/reset', [
            'reset_session' => $resetSession,
            'password' => 'newpassword123',
        ])->assertOk()
            ->assertJsonStructure(['token', 'user', 'wallet']);

        $this->assertNotEmpty((string) $reset->json('token'));
    }

    public function test_reset_session_is_single_use(): void
    {
        Http::fake(['www.cloudservicezm.com/*' => Http::response(['ok' => true], 200)]);
        Mail::fake();

        $this->app['config']->set('app.env', 'testing');

        $user = User::factory()->create([
            'email' => 'once@example.com',
            'password' => 'oldpassword123',
            'phone_number' => '260977111111',
            'phone_verified_at' => now(),
        ]);

        $req = $this->postJson('/api/password/otp/request', [
            'identifier' => '0977111111',
        ])->assertOk();
        $otpId = (int) $req->json('otp.id');

        $verify = $this->postJson('/api/password/otp/verify', [
            'email' => 'once@example.com',
            'otp_id' => $otpId,
            'otp_code' => '123456',
        ])->assertOk();

        $resetSession = (string) $verify->json('reset_session');

        $this->postJson('/api/password/reset', [
            'reset_session' => $resetSession,
            'password' => 'newpassword123',
        ])->assertOk();

        $this->postJson('/api/password/reset', [
            'reset_session' => $resetSession,
            'password' => 'anotherpassword123',
        ])->assertStatus(422);
    }
}

