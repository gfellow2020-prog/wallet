<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApiVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_v1_login_matches_legacy_response_shape(): void
    {
        $user = User::factory()->create([
            'email' => 'versioned@example.com',
            'password' => Hash::make('secret'),
            'phone_verified_at' => now(),
        ]);

        $legacy = $this->postJson('/api/login', [
            'email' => 'versioned@example.com',
            'password' => 'secret',
        ]);
        $v1 = $this->postJson('/api/v1/login', [
            'email' => 'versioned@example.com',
            'password' => 'secret',
        ]);

        $legacy->assertOk()->assertJsonStructure(['token', 'user', 'wallet']);
        $v1->assertOk()->assertJsonStructure(['token', 'user', 'wallet']);
        $this->assertSame($legacy->json('user.id'), $v1->json('user.id'));
    }

    public function test_v1_me_with_bearer_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $legacy = $this->withToken($token)->getJson('/api/me');
        $v1 = $this->withToken($token)->getJson('/api/v1/me');

        $legacy->assertOk();
        $v1->assertOk();
        $this->assertSame($legacy->json('user.id'), $v1->json('user.id'));
    }
}
