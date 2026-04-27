<?php

namespace Tests\Feature;

use App\Enums\KycStatus;
use App\Models\KycRecord;
use App\Models\PayoutAccount;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletSafetyAndPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_wallet_transactions_endpoint_is_paginated(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'available_balance' => 500,
            'currency' => 'ZMW',
        ]);

        for ($i = 1; $i <= 12; $i++) {
            WalletLedger::create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'type' => $i % 2 === 0 ? 'credit' : 'debit',
                'direction' => $i % 2 === 0 ? 'credit' : 'debit',
                'amount' => 10,
                'reference_type' => User::class,
                'reference_id' => $user->id,
                'balance_before' => 100,
                'balance_after' => 110,
                'metadata' => ['product_title' => "Entry {$i}"],
                'created_at' => now()->subMinutes($i),
                'updated_at' => now()->subMinutes($i),
            ]);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/wallet/transactions?per_page=5');

        $response->assertOk()
            ->assertJsonCount(5, 'transactions')
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_withdrawal_requires_verified_kyc(): void
    {
        $user = User::factory()->create();
        Wallet::query()->create([
            'user_id' => $user->id,
            'available_balance' => 100,
            'currency' => 'ZMW',
        ]);
        $account = PayoutAccount::create([
            'user_id' => $user->id,
            'type' => 'bank',
            'bank_name' => 'Demo Bank',
            'bank_code' => '001',
            'account_number' => '1234567890',
            'account_name' => 'Major Mac',
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/wallet/withdraw', [
            'amount' => 10,
            'payout_account_id' => $account->id,
        ])->assertStatus(403)
            ->assertJsonPath('kyc_status', KycStatus::NotSubmitted->value);
    }

    public function test_verified_user_can_reach_withdrawal_controller(): void
    {
        $user = User::factory()->create();
        Wallet::query()->create([
            'user_id' => $user->id,
            'available_balance' => 100,
            'currency' => 'ZMW',
        ]);
        $account = PayoutAccount::create([
            'user_id' => $user->id,
            'type' => 'bank',
            'bank_name' => 'Demo Bank',
            'bank_code' => '001',
            'account_number' => '1234567890',
            'account_name' => 'Major Mac',
            'is_default' => true,
        ]);

        KycRecord::create([
            'user_id' => $user->id,
            'full_name' => $user->name,
            'id_type' => 'national_id',
            'id_number' => '123456/78/9',
            'id_document_path' => 'kyc/documents/id.jpg',
            'selfie_path' => 'kyc/selfies/selfie.jpg',
            'status' => KycStatus::Verified,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/wallet/withdraw', [
            'amount' => 10,
            'payout_account_id' => $account->id,
        ])->assertStatus(503)
            ->assertJsonPath('message', 'Lenco is not configured. Contact support.');
    }
}
