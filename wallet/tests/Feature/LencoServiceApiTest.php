<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Services\LencoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Exercises LencoService against mocked Lenco API responses (no real network or secrets).
 */
class LencoServiceApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedLencoConfig(bool $includeKeys = true): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'lenco_base_url'],
            ['value' => 'https://api.lenco.co/access/v2']
        );
        SystemSetting::query()->updateOrCreate(
            ['key' => 'lenco_environment'],
            ['value' => 'sandbox']
        );
        SystemSetting::query()->updateOrCreate(
            ['key' => 'lenco_country'],
            ['value' => 'zm']
        );
        SystemSetting::query()->updateOrCreate(
            ['key' => 'lenco_account_id'],
            ['value' => 'acc-test-uuid']
        );
        SystemSetting::query()->updateOrCreate(
            ['key' => 'lenco_webhook_secret'],
            ['value' => '']
        );
        if ($includeKeys) {
            SystemSetting::query()->updateOrCreate(
                ['key' => 'lenco_api_key'],
                ['value' => 'pub-test-key']
            );
            SystemSetting::query()->updateOrCreate(
                ['key' => 'lenco_secret_key'],
                ['value' => 'secret-test-key']
            );
        } else {
            SystemSetting::query()->updateOrCreate(['key' => 'lenco_api_key'], ['value' => '']);
            SystemSetting::query()->updateOrCreate(['key' => 'lenco_secret_key'], ['value' => '']);
        }
    }

    public function test_configured_is_false_without_keys(): void
    {
        $this->seedLencoConfig(includeKeys: false);
        $lenco = app(LencoService::class);
        $this->assertFalse($lenco->configured());
    }

    public function test_configured_is_true_with_keys_and_base_url(): void
    {
        $this->seedLencoConfig();
        $lenco = app(LencoService::class);
        $this->assertTrue($lenco->configured());
    }

    public function test_banks_returns_success_and_sends_lenco_headers(): void
    {
        $this->seedLencoConfig();
        Http::fake([
            'https://api.lenco.co/access/v2/banks*' => Http::response([
                'status' => true,
                'data' => ['banks' => [['id' => 'bank-1', 'name' => 'Test Bank']]],
            ], 200),
        ]);

        $lenco = app(LencoService::class);
        $out = $lenco->banks();

        $this->assertTrue($out['success']);
        $this->assertStringContainsString('Test Bank', json_encode($out['data']));

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'banks')) {
                return false;
            }
            $this->assertSame('Bearer secret-test-key', $request->header('Authorization')[0] ?? null);
            $this->assertSame('pub-test-key', $request->header('X-Secret-Key')[0] ?? null);
            $this->assertSame('sandbox', $request->header('X-Environment')[0] ?? null);

            return true;
        });
    }

    public function test_verify_collection_parses_not_found_style_response(): void
    {
        $this->seedLencoConfig();
        Http::fake([
            'https://api.lenco.co/access/v2/collections/status/*' => Http::response([
                'message' => 'Collection not found for reference',
                'status' => false,
            ], 404),
        ]);

        $lenco = app(LencoService::class);
        $out = $lenco->verifyCollection('TEST-CONNECTION-123');

        $this->assertFalse($out['success']);
        $this->assertStringContainsString('not found', strtolower((string) $out['message']));
    }

    public function test_verify_collection_succeeds_when_status_true(): void
    {
        $this->seedLencoConfig();
        Http::fake([
            'https://api.lenco.co/access/v2/collections/status/*' => Http::response([
                'status' => true,
                'data' => ['state' => 'completed'],
            ], 200),
        ]);

        $lenco = app(LencoService::class);
        $out = $lenco->verifyCollection('a-real-uuid');

        $this->assertTrue($out['success']);
    }

    public function test_banks_with_only_secret_key_sends_bearer_and_no_x_secret_key(): void
    {
        SystemSetting::query()->updateOrCreate(['key' => 'lenco_base_url'], ['value' => 'https://api.lenco.co/access/v2']);
        SystemSetting::query()->updateOrCreate(['key' => 'lenco_environment'], ['value' => 'sandbox']);
        SystemSetting::query()->updateOrCreate(['key' => 'lenco_country'], ['value' => 'zm']);
        SystemSetting::query()->updateOrCreate(['key' => 'lenco_account_id'], ['value' => '']);
        SystemSetting::query()->updateOrCreate(['key' => 'lenco_webhook_secret'], ['value' => '']);
        SystemSetting::query()->updateOrCreate(['key' => 'lenco_api_key'], ['value' => '']);
        SystemSetting::query()->updateOrCreate(['key' => 'lenco_secret_key'], ['value' => 'only-secret-token']);

        Http::fake([
            'https://api.lenco.co/access/v2/banks*' => Http::response(['status' => true, 'data' => []], 200),
        ]);

        $lenco = app(LencoService::class);
        $this->assertTrue($lenco->configured());
        $lenco->banks();

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'banks')) {
                return false;
            }
            $this->assertSame('Bearer only-secret-token', $request->header('Authorization')[0] ?? null);
            $this->assertNull($request->header('X-Secret-Key')[0] ?? null);

            return true;
        });
    }

    public function test_balance_hits_accounts_endpoint(): void
    {
        $this->seedLencoConfig();
        Http::fake([
            'https://api.lenco.co/access/v2/accounts' => Http::response([
                'status' => true,
                'data' => ['accounts' => []],
            ], 200),
        ]);

        $lenco = app(LencoService::class);
        $out = $lenco->balance();

        $this->assertTrue($out['success']);
        Http::assertSent(fn ($r) => str_ends_with(rtrim($r->url(), '/'), '/accounts') || str_contains($r->url(), 'accounts'));
    }
}
