<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();

            // Buyer always pays the original listed price (gross_amount)
            $table->decimal('gross_amount', 15, 2); // original price buyer pays
            $table->decimal('admin_fee', 15, 2); // 1% of gross  -> platform revenue
            $table->decimal('cashback_amount', 15, 2); // 2% of gross  -> credited to buyer wallet
            $table->decimal('seller_net', 15, 2); // 97% of gross -> seller receives

            $table->string('status', 20)->default('completed'); // completed | refunded
            $table->string('reference')->unique();
            $table->timestamps();

            $table->index(['buyer_id',  'created_at']);
            $table->index(['seller_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sales');
    }
};
