<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\ExtraCashGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExtraCashGatewayWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'test-extracash-gateway-secret';

    public static function webhookPathProvider(): array
    {
        return [
            'extracash' => ['/webhook/extracash'],
            'legacy geepay url' => ['/webhook/geepay'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.extracash_gateway.webhook_secret' => self::WEBHOOK_SECRET,
            'services.extracash_gateway.webhook_header' => 'X-Geepay-Webhook-Secret',
        ]);
    }

    #[DataProvider('webhookPathProvider')]
    public function test_valid_credit_callback_credits_wallet_once(string $webhookPath): void
    {
        [$wallet, $transaction] = $this->makeTransaction('credit', 125.50, 0);

        $headers = ['X-Geepay-Webhook-Secret' => self::WEBHOOK_SECRET];
        $payload = [
            'reference' => $transaction->gateway_reference,
            'status' => 'success',
        ];

        $this->postJson($webhookPath, $payload, $headers)
            ->assertOk()
            ->assertJsonPath('message', 'Callback processed');

        $this->postJson($webhookPath, $payload, $headers)
            ->assertOk();

        $wallet->refresh();
        $transaction->refresh();

        $this->assertSame(125.50, (float) $wallet->available_balance);
        $this->assertSame('success', $transaction->gateway_status);
    }

    #[DataProvider('webhookPathProvider')]
    public function test_invalid_webhook_secret_is_rejected_without_mutation(string $webhookPath): void
    {
        [$wallet, $transaction] = $this->makeTransaction('credit', 80, 0);

        $this->postJson($webhookPath, [
            'reference' => $transaction->gateway_reference,
            'status' => 'success',
        ], [
            'X-Geepay-Webhook-Secret' => 'wrong-secret',
        ])->assertStatus(403);

        $wallet->refresh();
        $transaction->refresh();

        $this->assertSame(0.0, (float) $wallet->available_balance);
        $this->assertSame('pending', $transaction->gateway_status);
    }

    #[DataProvider('webhookPathProvider')]
    public function test_unknown_reference_returns_not_found(string $webhookPath): void
    {
        $this->postJson($webhookPath, [
            'reference' => 'missing-ref',
            'status' => 'success',
        ], [
            'X-Geepay-Webhook-Secret' => self::WEBHOOK_SECRET,
        ])->assertStatus(404);
    }

    #[DataProvider('webhookPathProvider')]
    public function test_failed_debit_callback_refunds_wallet_once(string $webhookPath): void
    {
        [$wallet, $transaction] = $this->makeTransaction('debit', 60, 40);

        $headers = ['X-Geepay-Webhook-Secret' => self::WEBHOOK_SECRET];
        $payload = [
            'reference' => $transaction->gateway_reference,
            'status' => 'failed',
        ];

        $this->postJson($webhookPath, $payload, $headers)->assertOk();
        $this->postJson($webhookPath, $payload, $headers)->assertOk();

        $wallet->refresh();
        $transaction->refresh();

        $this->assertSame(100.0, (float) $wallet->available_balance);
        $this->assertSame('failed', $transaction->gateway_status);
    }

    public function test_status_check_uses_same_idempotent_settlement_logic(): void
    {
        [$wallet, $transaction, $user] = $this->makeTransaction('credit', 55, 10);

        $mock = Mockery::mock(ExtraCashGatewayService::class);
        $mock->shouldReceive('collectStatus')
            ->twice()
            ->with($transaction->gateway_reference)
            ->andReturn([
                'data' => ['status' => 'success'],
            ]);
        $this->instance(ExtraCashGatewayService::class, $mock);

        $this->actingAs($user)
            ->getJson("/wallet/transaction/{$transaction->id}/status")
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->actingAs($user)
            ->getJson("/wallet/transaction/{$transaction->id}/status")
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $wallet->refresh();
        $this->assertSame(65.0, (float) $wallet->available_balance);
    }

    /**
     * @return array{0: Wallet, 1: Transaction, 2: User}
     */
    private function makeTransaction(string $type, float $amount, float $balance): array
    {
        $user = User::factory()->create();
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'available_balance' => $balance,
            'currency' => 'ZMW',
        ]);

        $transaction = $wallet->transactions()->create([
            'type' => $type,
            'amount' => $amount,
            'narration' => 'Webhook test',
            'gateway_reference' => 'ref-'.uniqid(),
            'gateway_status' => 'pending',
            'transacted_at' => now(),
        ]);

        return [$wallet, $transaction, $user];
    }
}
