<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletMobileApiContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.extracash_gateway.base_url' => 'https://gateway.mygeepay.com/api/v1',
            'services.extracash_gateway.client_id' => 'client-id',
            'services.extracash_gateway.auth_signature' => 'signature',
            'services.extracash_gateway.bearer_token' => 'token',
        ]);
    }

    public function test_mobile_name_lookup_endpoint_returns_name_and_normalized_phone(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Http::fake([
            'https://gateway.mygeepay.com/api/v1/mobile-money/name-lookup/*' => Http::response([
                'data' => [
                    'full_name' => 'Jane Recipient',
                ],
            ], 200),
        ]);

        $this->postJson('/api/wallet/name-lookup', [
            'phone_number' => '0977000000',
        ])->assertOk()
            ->assertJsonPath('name', 'Jane Recipient')
            ->assertJsonPath('phone_number', '260977000000');
    }

    public function test_mobile_send_endpoint_debits_available_balance_and_records_pending_transaction(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'available_balance' => 120,
            'currency' => 'ZMW',
        ]);

        Sanctum::actingAs($user);

        Http::fake([
            'https://gateway.mygeepay.com/api/v1/mobile-money/disburse' => Http::response([
                'status' => 'success',
                'message' => 'Queued',
            ], 200),
            'www.cloudservicezm.com/*' => Http::response(['ok' => true], 200),
        ]);

        $req = $this->postJson('/api/wallet/send/otp/request', [
            'phone_number' => '0977000000',
            'amount' => 20,
            'recipient' => 'Jane Recipient',
        ])->assertCreated();

        $otpId = (int) $req->json('otp.id');

        $this->postJson('/api/wallet/send/otp/verify', [
            'otp_id' => $otpId,
            'otp_code' => '123456',
        ])->assertOk()
            ->assertJsonPath('recipient.name', 'Jane Recipient')
            ->assertJsonPath('recipient.phone_number', '260977000000');

        $wallet->refresh();
        $this->assertSame(100.0, (float) $wallet->available_balance);

        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'debit',
            'phone_number' => '260977000000',
            'gateway_status' => 'pending',
        ]);
    }
}
