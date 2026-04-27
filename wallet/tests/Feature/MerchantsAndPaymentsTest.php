<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MerchantsAndPaymentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_merchant_store_generates_code_and_saves_cashback_rate(): void
    {
        $user = User::factory()->create(['email' => 'admin@merchants.test']);
        $this->seed(RolesAndPermissionsSeeder::class);
        $user->assignRole('merchant_admin');

        $this->actingAs($user)
            ->from(route('admin.merchants'))
            ->post(route('admin.merchants.store'), [
                'name' => 'Corner Store',
                'category' => 'groceries',
                'cashback_rate' => '0.03',
                'cashback_eligible' => '1',
            ])
            ->assertRedirect();

        $m = Merchant::query()->where('name', 'Corner Store')->first();
        $this->assertNotNull($m);
        $this->assertNotSame('', $m->code);
        $this->assertSame(0.03, (float) $m->cashback_rate);
    }

    public function test_public_merchants_list_includes_cashback_rate(): void
    {
        Merchant::query()->create([
            'name' => 'Café',
            'code' => 'CAFE1',
            'category' => 'food',
            'cashback_rate' => 0.025,
            'cashback_eligible' => true,
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $data = $this->getJson('/api/merchants')
            ->assertOk()
            ->json();

        $this->assertArrayHasKey('cashback_rate', $data['merchants'][0]);
        $this->assertEqualsWithDelta(0.025, (float) $data['merchants'][0]['cashback_rate'], 0.0001);
    }

    public function test_payment_merchant_resolves_through_order(): void
    {
        $user = User::factory()->create();
        $merchant = Merchant::query()->create([
            'name' => 'Shop',
            'code' => 'SHOP1',
            'category' => 'retail',
            'cashback_rate' => 0.02,
            'cashback_eligible' => true,
            'is_active' => true,
        ]);
        $order = Order::query()->create([
            'user_id' => $user->id,
            'merchant_id' => $merchant->id,
            'order_reference' => 'ORD-REL-1',
            'gross_amount' => 100,
            'eligible_amount' => 98.5,
            'fee_amount' => 1.5,
            'currency' => 'ZMW',
            'status' => 'pending',
        ]);
        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'payment_reference' => 'PAY-REL-1',
            'amount' => 100,
            'eligible_amount' => 100,
            'status' => PaymentStatus::Initiated,
        ]);
        $payment->load('merchant');
        $this->assertNotNull($payment->merchant);
        $this->assertSame($merchant->id, $payment->merchant->id);
    }
}
