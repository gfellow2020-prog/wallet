<?php

namespace Tests\Feature;

use App\Models\BuyRequest;
use App\Models\MissionDefinition;
use App\Models\Product;
use App\Models\RewardGrant;
use App\Models\StreakRewardDefinition;
use App\Models\User;
use App\Models\UserMission;
use App\Models\Wallet;
use Database\Seeders\RewardsMissionDefinitionSeeder;
use Database\Seeders\StreakRewardDefinitionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RewardsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RewardsMissionDefinitionSeeder::class);
        $this->seed(StreakRewardDefinitionSeeder::class);

        config([
            'services.extracash_gateway.base_url' => 'https://gateway.mygeepay.com/api/v1',
            'services.extracash_gateway.client_id' => 'client-id',
            'services.extracash_gateway.auth_signature' => 'signature',
            'services.extracash_gateway.bearer_token' => 'token',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_daily_check_in_is_idempotent_for_the_same_day(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Carbon::setTestNow('2026-04-24 08:00:00');

        $this->postJson('/api/rewards/check-in')
            ->assertOk()
            ->assertJsonPath('checked_in', true)
            ->assertJsonPath('summary.streak.current_count', 1)
            ->assertJsonPath('summary.missions.total_missions', 3);

        $this->postJson('/api/rewards/check-in')
            ->assertOk()
            ->assertJsonPath('checked_in', false)
            ->assertJsonPath('summary.streak.current_count', 1);

        $this->assertDatabaseHas('user_streaks', [
            'user_id' => $user->id,
            'streak_type' => 'daily_check_in',
            'current_count' => 1,
            'longest_count' => 1,
        ]);
    }

    public function test_streak_milestone_at_day_3_credits_reward_once(): void
    {
        $user = User::factory()->create();
        Wallet::query()->create([
            'user_id' => $user->id,
            'available_balance' => 0,
            'currency' => 'ZMW',
        ]);
        Sanctum::actingAs($user);

        Carbon::setTestNow('2026-04-24 08:00:00');
        $this->postJson('/api/rewards/check-in')
            ->assertOk()
            ->assertJsonPath('summary.streak.current_count', 1)
            ->assertJsonMissingPath('streak_milestone_grants');

        Carbon::setTestNow('2026-04-25 08:00:00');
        $this->postJson('/api/rewards/check-in')
            ->assertOk()
            ->assertJsonPath('summary.streak.current_count', 2);

        $def = StreakRewardDefinition::query()->where('code', 'streak_day_3')->firstOrFail();

        Carbon::setTestNow('2026-04-26 08:00:00');
        $this->postJson('/api/rewards/check-in')
            ->assertOk()
            ->assertJsonPath('summary.streak.current_count', 3)
            ->assertJsonPath('streak_milestone_grants.0.day_number', 3)
            ->assertJsonPath('streak_milestone_grants.0.reward_value', '1.00');

        $this->assertDatabaseHas('reward_grants', [
            'user_id' => $user->id,
            'source_type' => StreakRewardDefinition::class,
            'source_id' => $def->id,
            'reward_type' => 'wallet_bonus',
            'reward_value' => '1.00',
        ]);

        $this->assertDatabaseHas('wallet_ledgers', [
            'user_id' => $user->id,
            'type' => 'streak_reward',
            'direction' => 'credit',
        ]);
    }

    public function test_missed_day_resets_streak_after_gap(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Carbon::setTestNow('2026-04-24 08:00:00');
        $this->postJson('/api/rewards/check-in')->assertOk();

        Carbon::setTestNow('2026-04-25 08:00:00');
        $this->postJson('/api/rewards/check-in')->assertOk();

        Carbon::setTestNow('2026-04-27 08:00:00');
        $this->postJson('/api/rewards/check-in')
            ->assertOk()
            ->assertJsonPath('reset', true)
            ->assertJsonPath('summary.streak.current_count', 1)
            ->assertJsonPath('summary.streak.longest_count', 2);
    }

    public function test_qr_pay_marks_the_scan_mission_complete(): void
    {
        [$payer, $payerWallet] = $this->makeUserWithWallet(150);
        [$recipient] = $this->makeUserWithWallet(10);

        Sanctum::actingAs($payer);

        $this->postJson('/api/qr-pay', [
            'qr_payload' => base64_encode(json_encode([
                't' => 'payment',
                'uid' => $recipient->id,
            ])),
            'amount' => 25,
            'note' => 'Test mission',
        ])->assertOk();

        $mission = $this->missionFor($payer, 'scan_qr_once');

        $this->assertTrue($mission->is_completed);
        $this->assertSame(1, $mission->progress);
        $this->assertDatabaseHas('wallet_ledgers', [
            'wallet_id' => $payerWallet->id,
            'type' => 'transfer_send',
        ]);
    }

    public function test_send_money_marks_the_send_mission_complete(): void
    {
        [$user, $wallet] = $this->makeUserWithWallet(120);
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
        ])->assertOk();

        $mission = $this->missionFor($user, 'send_money_once');

        $this->assertTrue($mission->is_completed);
        $this->assertSame(1, $mission->progress);
        $wallet->refresh();
        $this->assertSame(100.0, (float) $wallet->available_balance);
    }

    public function test_single_purchase_marks_the_buy_mission_complete(): void
    {
        [$buyer, $buyerWallet] = $this->makeUserWithWallet(200);
        [$seller] = $this->makeUserWithWallet(0);
        $product = $this->makeProduct($seller, 60, 3);

        Sanctum::actingAs($buyer);

        $this->postJson("/api/products/{$product->id}/buy")->assertCreated();

        $mission = $this->missionFor($buyer, 'buy_once');
        $this->assertTrue($mission->is_completed);
        $this->assertSame(1, $mission->progress);

        $buyerWallet->refresh();
        $this->assertSame(141.2, round((float) $buyerWallet->available_balance, 2));
    }

    public function test_checkout_marks_the_buy_mission_complete_once_for_the_order(): void
    {
        [$buyer] = $this->makeUserWithWallet(300);
        [$seller] = $this->makeUserWithWallet(0);
        $productA = $this->makeProduct($seller, 40, 3);
        $productB = $this->makeProduct($seller, 30, 2);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/cart', ['product_id' => $productA->id, 'quantity' => 1])->assertCreated();
        $this->postJson('/api/cart', ['product_id' => $productB->id, 'quantity' => 1])->assertCreated();

        $this->postJson('/api/checkout')->assertCreated();

        $mission = $this->missionFor($buyer, 'buy_once');
        $this->assertTrue($mission->is_completed);
        $this->assertSame(1, $mission->progress);
    }

    public function test_buy_for_me_fulfillment_marks_the_buy_mission_complete(): void
    {
        [$requester] = $this->makeUserWithWallet(0);
        [$sponsor] = $this->makeUserWithWallet(250);
        [$seller] = $this->makeUserWithWallet(0);
        $product = $this->makeProduct($seller, 80, 2);

        $request = BuyRequest::create([
            'token' => (string) Str::uuid(),
            'product_id' => $product->id,
            'requester_id' => $requester->id,
            'target_user_id' => $sponsor->id,
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        Sanctum::actingAs($sponsor);

        $this->postJson("/api/buy-requests/{$request->token}/fulfill")->assertOk();

        $mission = $this->missionFor($sponsor, 'buy_once');
        $this->assertTrue($mission->is_completed);
        $this->assertSame(1, $mission->progress);
    }

    public function test_wallet_reward_claim_is_capped_by_admin_fee_and_only_works_once(): void
    {
        [$buyer, $wallet] = $this->makeUserWithWallet(200);
        [$seller] = $this->makeUserWithWallet(0);
        $product = $this->makeProduct($seller, 100, 1);
        Sanctum::actingAs($buyer);

        $this->postJson("/api/products/{$product->id}/buy")->assertCreated();

        $mission = $this->missionFor($buyer, 'buy_once');

        $this->postJson("/api/rewards/missions/{$mission->id}/claim")
            ->assertOk()
            ->assertJsonPath('reward.reward_type', 'wallet_bonus')
            ->assertJsonPath('reward.reward_value', '1.00')
            ->assertJsonPath('summary.missions.claimable_missions', 0);

        $wallet->refresh();
        $this->assertSame(103.0, (float) $wallet->available_balance);

        $this->postJson("/api/rewards/missions/{$mission->id}/claim")
            ->assertStatus(422)
            ->assertJsonPath('message', 'This mission reward has already been claimed.');

        $this->assertDatabaseHas('reward_grants', [
            'user_id' => $buyer->id,
            'user_mission_id' => $mission->id,
            'reward_type' => 'wallet_bonus',
            'reward_value' => '1.00',
        ]);
        $this->assertDatabaseHas('wallet_ledgers', [
            'wallet_id' => $wallet->id,
            'type' => 'mission_reward',
            'direction' => 'credit',
        ]);
        $this->assertSame(1, RewardGrant::query()->where('user_mission_id', $mission->id)->count());
    }

    public function test_wallet_reward_without_admin_fee_funding_is_rejected(): void
    {
        [$user, $wallet] = $this->makeUserWithWallet(25);
        Sanctum::actingAs($user);

        $mission = $this->completeMissionForUser($user, 'buy_once');

        $this->postJson("/api/rewards/missions/{$mission->id}/claim")
            ->assertStatus(422)
            ->assertJsonPath('message', 'This wallet reward is not funded by platform revenue.');

        $wallet->refresh();
        $this->assertSame(25.0, (float) $wallet->available_balance);
        $this->assertDatabaseMissing('wallet_ledgers', [
            'wallet_id' => $wallet->id,
            'type' => 'mission_reward',
        ]);
    }

    public function test_badge_reward_claim_creates_grant_without_wallet_credit(): void
    {
        [$user, $wallet] = $this->makeUserWithWallet(25);
        Sanctum::actingAs($user);

        $mission = $this->completeMissionForUser($user, 'send_money_once');

        $this->postJson("/api/rewards/missions/{$mission->id}/claim")
            ->assertOk()
            ->assertJsonPath('reward.reward_type', 'badge_unlock')
            ->assertJsonPath('summary.badges.0.code', 'connector');

        $wallet->refresh();
        $this->assertSame(25.0, (float) $wallet->available_balance);
        $this->assertDatabaseMissing('wallet_ledgers', [
            'wallet_id' => $wallet->id,
            'type' => 'mission_reward',
        ]);
        $this->assertDatabaseHas('reward_grants', [
            'user_id' => $user->id,
            'user_mission_id' => $mission->id,
            'reward_type' => 'badge_unlock',
        ]);
    }

    protected function missionFor(User $user, string $code): UserMission
    {
        return UserMission::query()
            ->where('user_id', $user->id)
            ->whereHas('missionDefinition', fn ($query) => $query->where('code', $code))
            ->firstOrFail();
    }

    protected function completeMissionForUser(User $user, string $code): UserMission
    {
        $definition = MissionDefinition::query()->where('code', $code)->firstOrFail();

        return UserMission::query()->create([
            'user_id' => $user->id,
            'mission_definition_id' => $definition->id,
            'period_date' => now()->toDateString(),
            'progress' => $definition->target_count,
            'is_completed' => true,
            'completed_at' => now(),
            'is_claimed' => false,
            'source_meta' => ['source_keys' => []],
        ]);
    }

    /**
     * @return array{0: User, 1: Wallet}
     */
    protected function makeUserWithWallet(float $balance): array
    {
        $user = User::factory()->create();
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'available_balance' => $balance,
            'currency' => 'ZMW',
        ]);

        return [$user, $wallet];
    }

    protected function makeProduct(User $seller, float $price, int $stock = 1): Product
    {
        return Product::query()->create([
            'user_id' => $seller->id,
            'title' => 'Mission Product '.Str::random(4),
            'description' => 'Product for rewards testing',
            'category' => 'Electronics',
            'price' => $price,
            'cashback_amount' => round($price * 0.02, 2),
            'cashback_rate' => 0.02,
            'condition' => 'new',
            'stock' => $stock,
            'is_active' => true,
            'latitude' => -15.4166,
            'longitude' => 28.2833,
            'location_label' => 'Lusaka',
        ]);
    }
}
