<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\UserDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserLookupAntiEnumerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_returns_user_when_found(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create(['extracash_number' => '1000000001']);

        Sanctum::actingAs($actor);

        $this->postJson('/api/users/lookup', [
            'extracash_number' => $other->extracash_number,
        ])
            ->assertOk()
            ->assertJsonPath('id', $other->id)
            ->assertJsonPath('extracash_number', $other->extracash_number);
    }

    public function test_not_found_and_short_number_share_same_message_and_status(): void
    {
        $actor = User::factory()->create();
        User::factory()->create(['extracash_number' => '2000000002']);

        Sanctum::actingAs($actor);

        $notFound = $this->postJson('/api/users/lookup', [
            'extracash_number' => '9999999999',
        ]);
        $notFound->assertNotFound()
            ->assertJsonPath('message', UserDirectory::EXTRA_CASH_LOOKUP_NOT_FOUND);

        $tooShort = $this->postJson('/api/users/lookup', [
            'extracash_number' => '12',
        ]);
        $tooShort->assertNotFound()
            ->assertJsonPath('message', UserDirectory::EXTRA_CASH_LOOKUP_NOT_FOUND);

        $this->assertSame(
            $notFound->json('message'),
            $tooShort->json('message'),
            'Invalid length and missing user should not be distinguishable by message.'
        );
    }

    public function test_self_lookup_returns_neutral_message(): void
    {
        $actor = User::factory()->create(['extracash_number' => '3000000003']);

        Sanctum::actingAs($actor);

        $this->postJson('/api/users/lookup', [
            'extracash_number' => $actor->extracash_number,
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', UserDirectory::EXTRA_CASH_LOOKUP_SELF);
    }
}
