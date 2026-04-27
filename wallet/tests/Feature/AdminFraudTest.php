<?php

namespace Tests\Feature;

use App\Models\FraudFlag;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFraudTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create(['email' => 'admin@fraud.test']);
        $admin->assignRole('risk');

        return $admin;
    }

    public function test_non_admin_cannot_view_fraud_page(): void
    {
        $user = User::factory()->create(['email' => 'user@fraud.test']);

        $this->actingAs($user)
            ->get(route('admin.fraud'))
            ->assertForbidden();
    }

    public function test_admin_sees_fraud_flags_and_resolves_them(): void
    {
        $admin = $this->adminUser();
        $subject = User::factory()->create(['email' => 'flagged@fraud.test']);
        $flag = FraudFlag::query()->create([
            'user_id' => $subject->id,
            'flag_type' => 'test_flag',
            'status' => 'flagged',
            'notes' => 'Unit test flag',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.fraud'))
            ->assertOk()
            ->assertSee('test_flag')
            ->assertSee('Unit test flag');

        $this->assertNull($flag->fresh()->resolved_at);

        $this->actingAs($admin)
            ->post(route('admin.fraud.resolve', $flag))
            ->assertRedirect();

        $flag->refresh();
        $this->assertNotNull($flag->resolved_at);
        $this->assertSame('resolved', $flag->status);
        $this->assertSame($admin->id, $flag->reviewed_by);
    }

    public function test_fraud_list_filters_to_open_by_default(): void
    {
        $admin = $this->adminUser();
        $u = User::factory()->create();
        FraudFlag::query()->create([
            'user_id' => $u->id,
            'flag_type' => 'open_only_type',
            'status' => 'flagged',
            'notes' => 'Still open for review',
        ]);
        FraudFlag::query()->create([
            'user_id' => $u->id,
            'flag_type' => 'resolved_only_type',
            'status' => 'resolved',
            'notes' => 'Cleared in ops',
            'resolved_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.fraud', ['status' => 'open']))
            ->assertOk()
            ->assertSee('open_only_type')
            ->assertDontSee('resolved_only_type');

        $this->actingAs($admin)
            ->get(route('admin.fraud', ['status' => 'resolved']))
            ->assertOk()
            ->assertSee('resolved_only_type');
    }
}
