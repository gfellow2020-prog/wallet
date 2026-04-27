<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_wallet_page_renders_for_mobile_layout(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'available_balance' => 12500,
            'currency' => 'ZMW',
        ]);

        $wallet->transactions()->create([
            'type' => 'credit',
            'amount' => 2500,
            'narration' => 'Test top-up',
            'transacted_at' => now(),
        ]);

        $this->actingAs($user);

        $response = $this->get('/wallet');

        $response->assertOk();
        $response->assertSee('ExtraCash Wallet');
        $response->assertSee('Test top-up');
    }

    public function test_guest_is_redirected_to_login_for_protected_wallet_page(): void
    {
        $response = $this->get('/wallet');

        $response->assertRedirect(route('login'));
    }
}
