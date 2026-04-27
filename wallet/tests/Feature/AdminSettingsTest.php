<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\LencoService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_settings_preserves_lenco_secret_when_submitted_empty(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.com', 'password' => 'password']);
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->assignRole('super_admin');

        SystemSetting::query()->updateOrCreate(
            ['key' => 'lenco_secret_key'],
            ['value' => 'sk_saved_secret_value_12345']
        );

        $this->actingAs($admin)
            ->from(route('admin.settings'))
            ->post(route('admin.settings.update'), [
                'settings' => [
                    'lenco_api_key' => 'lnc_key',
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
                ],
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertSame(
            'sk_saved_secret_value_12345',
            SystemSetting::where('key', 'lenco_secret_key')->value('value')
        );
    }

    public function test_saving_settings_preserves_lenco_api_key_when_submitted_empty(): void
    {
        $admin = User::factory()->create(['email' => 'admin2@example.com', 'password' => 'password']);
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->assignRole('super_admin');

        SystemSetting::query()->updateOrCreate(
            ['key' => 'lenco_api_key'],
            ['value' => 'lnc_saved_key_value_999']
        );
        SystemSetting::query()->updateOrCreate(
            ['key' => 'lenco_secret_key'],
            ['value' => 'sk_foo']
        );
        SystemSetting::query()->updateOrCreate(
            ['key' => 'lenco_base_url'],
            ['value' => 'https://api.lenco.co/access/v2']
        );

        $this->actingAs($admin)
            ->from(route('admin.settings'))
            ->post(route('admin.settings.update'), [
                'settings' => [
                    'lenco_api_key' => '',
                    'lenco_secret_key' => 'sk_foo',
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
                ],
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertSame(
            'lnc_saved_key_value_999',
            SystemSetting::where('key', 'lenco_api_key')->value('value')
        );
    }

    public function test_lenco_test_connection_post_succeeds_when_api_reports_not_found_for_fake_reference(): void
    {
        $admin = User::factory()->create(['email' => 'ops@example.com', 'password' => 'password']);
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->assignRole('ops');

        $lenco = Mockery::mock(LencoService::class);
        $lenco->shouldReceive('configured')->once()->andReturn(true);
        $lenco->shouldReceive('verifyCollection')->once()->andReturnUsing(static fn () => [
            'success' => false,
            'message' => 'Collection not found',
        ]);
        $this->app->instance(LencoService::class, $lenco);

        $this->actingAs($admin)
            ->from(route('admin.settings'))
            ->post(route('admin.settings.lenco.test'))
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHas('success');
    }

    public function test_lenco_test_connection_post_shows_error_when_lenco_not_configured(): void
    {
        $admin = User::factory()->create(['email' => 'a@example.com', 'password' => 'password']);
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->assignRole('ops');

        $lenco = Mockery::mock(LencoService::class);
        $lenco->shouldReceive('configured')->once()->andReturn(false);
        $lenco->shouldNotReceive('verifyCollection');
        $this->app->instance(LencoService::class, $lenco);

        $this->actingAs($admin)
            ->post(route('admin.settings.lenco.test'))
            ->assertRedirect(route('admin.settings'))
            ->assertSessionHas('error');
    }

    public function test_get_lenco_test_redirects_to_settings(): void
    {
        $admin = User::factory()->create(['email' => 'b@example.com', 'password' => 'password']);
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->assignRole('ops');

        $this->actingAs($admin)
            ->get('/admin/settings/lenco/test')
            ->assertRedirect(route('admin.settings'));
    }

    public function test_saving_settings_preserves_smartdata_api_key_when_submitted_empty(): void
    {
        $admin = User::factory()->create(['email' => 'compliance@example.com', 'password' => 'password']);
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->assignRole('super_admin');

        SystemSetting::query()->updateOrCreate(
            ['key' => 'smartdata_api_key'],
            ['value' => 'saved_smartdata_key_xyz']
        );

        $this->actingAs($admin)
            ->from(route('admin.settings'))
            ->post(route('admin.settings.update'), [
                'settings' => [
                    'lenco_api_key' => 'lnc_key',
                    'lenco_secret_key' => 'sk_foo',
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
                ],
            ])
            ->assertRedirect(route('admin.settings'));

        $this->assertSame(
            'saved_smartdata_key_xyz',
            SystemSetting::where('key', 'smartdata_api_key')->value('value')
        );
    }
}
