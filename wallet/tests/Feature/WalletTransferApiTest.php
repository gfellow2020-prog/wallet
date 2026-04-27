<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletLedger;
use App\Services\LedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletTransferApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_payment_transfers_funds_atomically_and_creates_both_ledger_rows(): void
    {
        [$payer, $payerWallet] = $this->makeUserWithWallet(120);
        [$recipient, $recipientWallet] = $this->makeUserWithWallet(10);

        Sanctum::actingAs($payer);

        $this->postJson('/api/qr-pay', [
            'qr_payload' => $this->qrPayloadFor($recipient),
            'amount' => 50,
            'note' => 'Lunch split',
        ])->assertOk()
            ->assertJsonPath('recipient.id', $recipient->id)
            ->assertJsonPath('reference', fn ($value) => is_string($value) && $value !== '');

        $payerWallet->refresh();
        $recipientWallet->refresh();

        $this->assertSame(70.0, (float) $payerWallet->available_balance);
        $this->assertSame(60.0, (float) $recipientWallet->available_balance);

        $this->assertDatabaseCount('wallet_ledgers', 2);
        $this->assertDatabaseHas('wallet_ledgers', [
            'wallet_id' => $payerWallet->id,
            'type' => 'transfer_send',
            'direction' => 'debit',
            'reference_type' => User::class,
            'reference_id' => $recipient->id,
        ]);
        $this->assertDatabaseHas('wallet_ledgers', [
            'wallet_id' => $recipientWallet->id,
            'type' => 'transfer_receive',
            'direction' => 'credit',
            'reference_type' => User::class,
            'reference_id' => $payer->id,
        ]);
    }

    public function test_payment_qr_preview_returns_public_recipient_fields(): void
    {
        [$payer] = $this->makeUserWithWallet(50);
        [$recipient] = $this->makeUserWithWallet(10);

        Sanctum::actingAs($payer);

        $this->postJson('/api/wallet/payment-qr-preview', [
            'qr_payload' => $this->qrPayloadFor($recipient),
        ])->assertOk()
            ->assertJsonPath('recipient.id', $recipient->id)
            ->assertJsonPath('recipient.name', $recipient->name)
            ->assertJsonPath('recipient.extracash_number', $recipient->extracash_number);
    }

    public function test_payment_qr_preview_rejects_scanning_own_code(): void
    {
        [$payer] = $this->makeUserWithWallet(50);

        Sanctum::actingAs($payer);

        $this->postJson('/api/wallet/payment-qr-preview', [
            'qr_payload' => $this->qrPayloadFor($payer),
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Invalid recipient.');
    }

    public function test_payment_qr_preview_rejects_invalid_payload(): void
    {
        [$payer] = $this->makeUserWithWallet(50);

        Sanctum::actingAs($payer);

        $this->postJson('/api/wallet/payment-qr-preview', [
            'qr_payload' => 'not-valid-base64!!!',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Invalid QR code.');
    }

    public function test_qr_payment_replays_identical_success_when_idempotency_key_is_reused(): void
    {
        [$payer, $payerWallet] = $this->makeUserWithWallet(120);
        [$recipient, $recipientWallet] = $this->makeUserWithWallet(10);

        Sanctum::actingAs($payer);

        $payload = [
            'qr_payload' => $this->qrPayloadFor($recipient),
            'amount' => 50,
            'note' => 'Test idempotency',
        ];
        $headers = ['Idempotency-Key' => 'test-key-qr-'.uniqid()];

        $first = $this->postJson('/api/qr-pay', $payload, $headers)->assertOk();
        $ref = $first->json('reference');

        $second = $this->postJson('/api/qr-pay', $payload, $headers)->assertOk();
        $this->assertSame($ref, $second->json('reference'));
        $this->assertSame('true', $second->headers->get('Idempotency-Replayed'));

        $payerWallet->refresh();
        $recipientWallet->refresh();
        $this->assertSame(70.0, (float) $payerWallet->available_balance);
        $this->assertSame(60.0, (float) $recipientWallet->available_balance);
        $this->assertDatabaseCount('wallet_ledgers', 2);
    }

    public function test_qr_payment_returns_409_when_idempotency_key_reused_with_different_body(): void
    {
        [$payer] = $this->makeUserWithWallet(120);
        [$recipient] = $this->makeUserWithWallet(10);

        Sanctum::actingAs($payer);

        $key = 'test-key-mismatch-'.uniqid();
        $headers = ['Idempotency-Key' => $key];

        $this->postJson('/api/qr-pay', [
            'qr_payload' => $this->qrPayloadFor($recipient),
            'amount' => 10,
        ], $headers)->assertOk();

        $this->postJson('/api/qr-pay', [
            'qr_payload' => $this->qrPayloadFor($recipient),
            'amount' => 11,
        ], $headers)->assertStatus(409)
            ->assertJsonPath('message', 'Idempotency-Key was reused with a different request body.');
    }

    public function test_qr_payment_rejects_when_balance_is_insufficient(): void
    {
        [$payer, $payerWallet] = $this->makeUserWithWallet(15);
        [$recipient, $recipientWallet] = $this->makeUserWithWallet(20);

        Sanctum::actingAs($payer);

        $this->postJson('/api/qr-pay', [
            'qr_payload' => $this->qrPayloadFor($recipient),
            'amount' => 50,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Insufficient available balance.');

        $payerWallet->refresh();
        $recipientWallet->refresh();

        $this->assertSame(15.0, (float) $payerWallet->available_balance);
        $this->assertSame(20.0, (float) $recipientWallet->available_balance);
        $this->assertDatabaseCount('wallet_ledgers', 0);
    }

    public function test_transfer_rolls_back_balances_if_recipient_ledger_write_fails(): void
    {
        [, $payerWallet] = $this->makeUserWithWallet(100);
        [, $recipientWallet] = $this->makeUserWithWallet(25);

        $ledger = new class extends LedgerService
        {
            protected function createLedgerEntry(array $attributes): WalletLedger
            {
                if (($attributes['type'] ?? null) === 'transfer_receive') {
                    throw new \RuntimeException('Simulated recipient ledger failure.');
                }

                return parent::createLedgerEntry($attributes);
            }
        };

        try {
            $ledger->transfer(
                $payerWallet,
                $recipientWallet,
                40,
                User::class,
                $recipientWallet->user_id,
                $payerWallet->user_id,
                'transfer_send',
                'transfer_receive',
                ['reference' => 'ref-1'],
                ['reference' => 'ref-1']
            );

            $this->fail('Expected the transfer to throw.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated recipient ledger failure.', $e->getMessage());
        }

        $payerWallet->refresh();
        $recipientWallet->refresh();

        $this->assertSame(100.0, (float) $payerWallet->available_balance);
        $this->assertSame(25.0, (float) $recipientWallet->available_balance);
        $this->assertDatabaseCount('wallet_ledgers', 0);
    }

    private function qrPayloadFor(User $recipient): string
    {
        return base64_encode(json_encode([
            't' => 'payment',
            'uid' => $recipient->id,
            'v' => 1,
        ]));
    }

    /**
     * @return array{0: User, 1: Wallet}
     */
    private function makeUserWithWallet(float $balance): array
    {
        $user = User::factory()->create();
        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'available_balance' => $balance,
            'currency' => 'ZMW',
        ]);

        return [$user, $wallet];
    }
}
