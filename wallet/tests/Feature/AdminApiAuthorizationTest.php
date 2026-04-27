<?php

namespace Tests\Feature;

use App\Enums\KycStatus;
use App\Models\KycRecord;
use App\Models\Merchant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminApiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_user_is_forbidden_from_admin_api_routes(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/kyc')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Admin access is required for this action.');

        $this->postJson('/api/admin/merchants', [
            'name' => 'Power Shop',
            'code' => 'POWER',
            'category' => 'utilities',
        ])->assertStatus(403);
    }

    public function test_admin_user_can_list_and_review_kyc_and_manage_merchants(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $member = User::factory()->create();
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->assignRole('super_admin');

        $kyc = KycRecord::create([
            'user_id' => $member->id,
            'full_name' => $member->name,
            'id_type' => 'national_id',
            'id_number' => '123456/78/9',
            'id_document_path' => 'kyc/documents/id.jpg',
            'selfie_path' => 'kyc/selfies/selfie.jpg',
            'status' => KycStatus::Pending,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/kyc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $kyc->id)
            ->assertJsonPath('data.0.user.name', $member->name);

        $this->postJson("/api/admin/kyc/{$kyc->id}/review", [
            'decision' => 'approved',
            'review_notes' => 'Documents verified.',
        ])->assertOk()
            ->assertJsonPath('kyc.status', KycStatus::Verified->value);

        $merchantResponse = $this->postJson('/api/admin/merchants', [
            'name' => 'Fresh Mart',
            'code' => 'FRESH',
            'category' => 'groceries',
            'cashback_eligible' => true,
            'is_active' => true,
        ])->assertCreated()
            ->assertJsonPath('merchant.code', 'FRESH');

        $merchantId = $merchantResponse->json('merchant.id');

        $this->postJson('/api/admin/merchants', [
            'name' => 'Codeless Mart',
            'category' => 'retail',
        ])->assertCreated()
            ->assertJsonPath('merchant.name', 'Codeless Mart');

        $this->assertNotEmpty(
            Merchant::query()->where('name', 'Codeless Mart')->value('code')
        );

        $this->patchJson("/api/admin/merchants/{$merchantId}", [
            'name' => 'Fresh Mart Express',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('merchant.name', 'Fresh Mart Express')
            ->assertJsonPath('merchant.is_active', false);

        $this->assertDatabaseHas('kyc_records', [
            'id' => $kyc->id,
            'status' => KycStatus::Verified->value,
            'reviewed_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('merchants', [
            'id' => $merchantId,
            'name' => 'Fresh Mart Express',
            'code' => 'FRESH',
            'is_active' => 0,
        ]);
    }
}
