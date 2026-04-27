<?php

namespace Tests\Feature;

use App\Models\BuyRequest;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductFeeSplitAndAdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_product_purchase_uses_public_cashback_and_internal_admin_fee_split(): void
    {
        [$buyer] = $this->makeUserWithWallet(200);
        [$seller] = $this->makeUserWithWallet(0);
        $product = $this->makeProduct($seller, 100);

        Sanctum::actingAs($buyer);

        $response = $this->postJson("/api/products/{$product->id}/buy")
            ->assertCreated()
            ->assertJsonPath('sale.you_paid', 100)
            ->assertJsonPath('sale.cashback_earned', 2);

        $this->assertArrayNotHasKey('admin_fee', $response->json('sale'));

        $this->assertDatabaseHas('product_sales', [
            'product_id' => $product->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'gross_amount' => 100,
            'cashback_amount' => 2,
            'admin_fee' => 1,
            'seller_net' => 97,
            'status' => 'completed',
        ]);
    }

    public function test_cart_checkout_uses_same_split_without_exposing_admin_fee_to_buyer(): void
    {
        [$buyer] = $this->makeUserWithWallet(300);
        [$seller] = $this->makeUserWithWallet(0);
        $product = $this->makeProduct($seller, 50, 5);

        CartItem::query()->create([
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        Sanctum::actingAs($buyer);

        $response = $this->postJson('/api/checkout')
            ->assertCreated()
            ->assertJsonPath('order.total_paid', 100)
            ->assertJsonPath('order.cashback_earned', 2);

        $this->assertArrayNotHasKey('admin_fee', $response->json('order'));

        $this->assertDatabaseHas('product_sales', [
            'product_id' => $product->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'quantity' => 2,
            'gross_amount' => 100,
            'cashback_amount' => 2,
            'admin_fee' => 1,
            'seller_net' => 97,
            'status' => 'completed',
        ]);
    }

    public function test_buy_for_me_fulfillment_uses_same_split_without_exposing_admin_fee(): void
    {
        [$requester] = $this->makeUserWithWallet(0);
        [$sponsor] = $this->makeUserWithWallet(300);
        [$seller] = $this->makeUserWithWallet(0);
        $product = $this->makeProduct($seller, 200);

        $buyRequest = BuyRequest::query()->create([
            'token' => (string) Str::uuid(),
            'product_id' => $product->id,
            'requester_id' => $requester->id,
            'target_user_id' => $sponsor->id,
            'status' => 'pending',
            'expires_at' => now()->addDay(),
        ]);

        Sanctum::actingAs($sponsor);

        $response = $this->postJson("/api/buy-requests/{$buyRequest->token}/fulfill")
            ->assertOk()
            ->assertJsonPath('sale.gross_amount', 200)
            ->assertJsonPath('sale.cashback_amount', 4);

        $this->assertArrayNotHasKey('admin_fee', $response->json('sale'));

        $this->assertDatabaseHas('product_sales', [
            'product_id' => $product->id,
            'buyer_id' => $sponsor->id,
            'seller_id' => $seller->id,
            'gross_amount' => 200,
            'cashback_amount' => 4,
            'admin_fee' => 2,
            'seller_net' => 194,
            'status' => 'completed',
        ]);
    }

    public function test_cart_payload_keeps_admin_fee_internal(): void
    {
        [$buyer] = $this->makeUserWithWallet(300);
        [$seller] = $this->makeUserWithWallet(0);
        $product = $this->makeProduct($seller, 100, 2);

        CartItem::query()->create([
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        Sanctum::actingAs($buyer);

        $response = $this->getJson('/api/cart')
            ->assertOk()
            ->assertJsonPath('totals.gross', 100)
            ->assertJsonPath('totals.cashback', 2);

        $this->assertArrayNotHasKey('admin_fee', $response->json('totals'));
    }

    public function test_admin_dashboard_renders_marketplace_fee_kpis(): void
    {
        $admin = User::factory()->create(['email' => 'admin@dashboard.test']);
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin->assignRole('super_admin');

        [$buyer] = $this->makeUserWithWallet(0);
        [$seller] = $this->makeUserWithWallet(0);
        $product = $this->makeProduct($seller, 100);

        ProductSale::query()->create([
            'product_id' => $product->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'quantity' => 1,
            'gross_amount' => 100,
            'cashback_amount' => 2,
            'admin_fee' => 1,
            'seller_net' => 97,
            'status' => 'completed',
            'reference' => 'SALE-'.strtoupper(Str::random(10)),
        ]);

        ProductSale::query()->create([
            'product_id' => $product->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'quantity' => 1,
            'gross_amount' => 200,
            'cashback_amount' => 4,
            'admin_fee' => 2,
            'seller_net' => 194,
            'status' => 'completed',
            'reference' => 'SALE-'.strtoupper(Str::random(10)),
        ]);

        $this->actingAs($admin);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Gross sales volume')
            ->assertSee('ZMW 300.00')
            ->assertSee('Admin fee (1%)')
            ->assertSee('ZMW 3.00')
            ->assertSee('Marketplace cashback (2%)')
            ->assertSee('ZMW 6.00')
            ->assertSee('To sellers (97%)')
            ->assertSee('ZMW 291.00')
            ->assertSee('avg order ZMW 150.00');
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

    private function makeProduct(User $seller, float $price, int $stock = 1): Product
    {
        return Product::query()->create([
            'user_id' => $seller->id,
            'title' => 'KPI Product '.Str::random(4),
            'description' => 'Marketplace KPI test product',
            'category' => 'Electronics',
            'price' => $price,
            'cashback_amount' => round($price * 0.02, 2),
            'cashback_rate' => 0.02,
            'condition' => 'new',
            'stock' => $stock,
            'is_active' => true,
            'latitude' => -15.4166,
            'longitude' => 28.2833,
            'location_label' => 'Lusaka',
        ]);
    }
}
