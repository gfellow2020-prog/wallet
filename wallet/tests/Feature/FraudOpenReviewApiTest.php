<?php

namespace Tests\Feature;

use App\Models\FraudFlag;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FraudOpenReviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_includes_open_fraud_review_flag(): void
    {
        $user = User::factory()->create();
        Wallet::query()->create([
            'user_id' => $user->id,
            'available_balance' => 0,
            'currency' => 'ZMW',
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.has_open_fraud_review', false);

        FraudFlag::query()->create([
            'user_id' => $user->id,
            'flag_type' => 'test',
            'status' => 'flagged',
            'notes' => 'Review this account',
        ]);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.has_open_fraud_review', true);
    }
}
