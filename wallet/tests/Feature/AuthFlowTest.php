<?php

namespace Tests\Feature;

use App\Mail\PasswordResetLinkMail;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_get_a_wallet(): void
    {
        $response = $this->post('/register', [
            'name' => 'Major Mac',
            'email' => 'major@example.com',
            'phone_number' => '0977000000',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertRedirect(route('phone.verify.show'));

        $user = User::where('email', 'major@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->wallet);
        $this->assertSame('ZMW', $user->wallet->currency);

        // Complete phone verification (OTP is fixed in tests as 123456).
        $this->withSession([
            'phone_verify_user_id' => $user->id,
            'phone_verify_otp_id' => \App\Models\UserOtp::query()
                ->where('user_id', $user->id)
                ->where('purpose', 'phone_verify')
                ->latest('id')
                ->value('id'),
        ])->post('/verify-phone', [
            'otp_code' => '123456',
        ])->assertRedirect(route('wallet.home'));

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => 'password123',
            'phone_verified_at' => now(),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('wallet.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_forgot_password_queues_reset_email_for_existing_user(): void
    {
        Mail::fake();
        Queue::fake();

        $user = User::factory()->create([
            'email' => 'exists@example.com',
        ]);

        $response = $this->post('/password/email', [
            'email' => $user->email,
        ]);

        $response->assertSessionHas('status', 'If your email exists in our system, we have emailed your password reset link.');

        $this->assertDatabaseHas('password_resets', [
            'email' => $user->email,
        ]);

        Mail::assertQueued(PasswordResetLinkMail::class, function (PasswordResetLinkMail $mail) use ($user) {
            return str_contains($mail->url, urlencode($user->email));
        });

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $user->id,
            'type' => 'security_password_reset_requested',
        ]);
    }

    public function test_forgot_password_does_not_send_email_for_unknown_user(): void
    {
        Mail::fake();

        $response = $this->post('/password/email', [
            'email' => 'unknown@example.com',
        ]);

        $response->assertSessionHas('status', 'If your email exists in our system, we have emailed your password reset link.');

        $this->assertDatabaseMissing('password_resets', [
            'email' => 'unknown@example.com',
        ]);

        Mail::assertNothingQueued();
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'resetme@example.com',
            'password' => 'oldpassword',
        ]);

        DB::table('password_resets')->insert([
            'email' => $user->email,
            'token' => 'valid-token-123',
            'created_at' => now(),
        ]);

        $response = $this->post('/password/reset', [
            'email' => $user->email,
            'token' => 'valid-token-123',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect(route('wallet.home'));

        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
        $this->assertDatabaseMissing('password_resets', [
            'email' => $user->email,
        ]);
        $this->assertAuthenticatedAs($user);
    }
}
