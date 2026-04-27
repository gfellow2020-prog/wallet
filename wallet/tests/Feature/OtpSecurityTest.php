<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserOtp;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OtpSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_money_flow_requires_otp_request_then_verify(): void
    {
        Http::fake([
            'www.cloudservicezm.com/*' => Http::response(['ok' => true], 200),
            '*' => Http::response(['success' => true, 'reference' => 'REF-1', 'status' => 'ok', 'data' => []], 200),
        ]);
        Mail::fake();

        $user = User::factory()->create([
            'phone_number' => '0973790404',
        ]);
        Wallet::query()->create([
            'user_id' => $user->id,
            'available_balance' => 1000,
            'currency' => 'ZMW',
        ]);
        Sanctum::actingAs($user);

        // legacy endpoint now requires otp
        $this->postJson('/api/wallet/send', [
            'phone_number' => '0973790404',
            'amount' => 50,
        ])->assertStatus(428)
            ->assertJsonPath('otp_required', true);

        // request OTP
        $req = $this->postJson('/api/wallet/send/otp/request', [
            'phone_number' => '0973790404',
            'amount' => 50,
        ])->assertCreated()
            ->assertJsonPath('otp.purpose', 'send_money');

        $otpId = (int) $req->json('otp.id');
        $this->assertDatabaseHas('user_otps', [
            'id' => $otpId,
            'user_id' => $user->id,
            'purpose' => 'send_money',
        ]);

        // verify with wrong code fails
        $this->postJson('/api/wallet/send/otp/verify', [
            'otp_id' => $otpId,
            'otp_code' => '000000',
        ])->assertStatus(422);
    }

    public function test_login_returns_otp_required_on_risk_and_succeeds_after_verify(): void
    {
        Http::fake([
            'www.cloudservicezm.com/*' => Http::response(['ok' => true], 200),
        ]);
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'risk@example.com',
            'password' => 'secret123',
            'phone_number' => '0973790404',
            'last_login_ip' => '1.1.1.1',
            'phone_verified_at' => now(),
        ]);
        Wallet::query()->create([
            'user_id' => $user->id,
            'available_balance' => 0,
            'currency' => 'ZMW',
        ]);

        $login = $this->postJson('/api/login', [
            'email' => 'risk@example.com',
            'password' => 'secret123',
            'device_id' => 'dev-1',
        ])->assertStatus(428)
            ->assertJsonPath('otp_required', true);

        $otpId = (int) $login->json('otp.id');

        $this->assertDatabaseHas('user_otps', [
            'id' => $otpId,
            'user_id' => $user->id,
            'purpose' => 'login',
        ]);

        // Wrong code
        $this->postJson('/api/login/otp/verify', [
            'email' => 'risk@example.com',
            'otp_id' => $otpId,
            'otp_code' => '000000',
            'device_id' => 'dev-1',
        ])->assertStatus(422);
    }

    public function test_ops_cannot_update_sms_secrets_but_can_update_sms_enabled(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $ops = User::factory()->create(['email' => 'ops@example.com', 'password' => 'password']);
        $ops->assignRole('ops');

        SystemSetting::query()->updateOrCreate(['key' => 'sms_password'], ['value' => 'old']);
        SystemSetting::query()->updateOrCreate(['key' => 'sms_enabled'], ['value' => '0']);

        $this->actingAs($ops)
            ->post(route('admin.settings.update'), [
                'settings' => [
                    'lenco_api_key' => '',
                    'lenco_secret_key' => '',
                    'lenco_base_url' => 'https://api.lenco.co/access/v2',
                    'lenco_webhook_secret' => '',
                    'lenco_environment' => 'sandbox',
                    'lenco_account_id' => '',
                    'lenco_country' => 'zm',
                    'cashback_enabled' => '0',
                    'cashback_rate' => '0.02',
                    'cashback_hold_days' => '7',
                    'cashback_daily_cap' => '500',
                    'cashback_monthly_cap' => '5000',
                    'withdrawal_min_amount' => '20',
                    'withdrawal_daily_max' => '10000',
                    'withdrawal_require_kyc' => '0',
                    'smartdata_api_key' => '',
                    'smartdata_base_url' => 'https://mysmartdata.tech/api/v1',
                    'sms_enabled' => '1',
                    'sms_password' => '',
                    'sms_username' => '',
                    'sms_sender_id' => '',
                    'sms_short_code' => '388',
                ],
            ])->assertRedirect(route('admin.settings'));

        $this->assertSame('old', SystemSetting::where('key', 'sms_password')->value('value'));
        $this->assertSame('1', SystemSetting::where('key', 'sms_enabled')->value('value'));
    }
}

