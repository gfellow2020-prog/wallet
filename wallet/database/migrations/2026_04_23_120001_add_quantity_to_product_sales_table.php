<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_sales', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->after('seller_id');
            $table->string('checkout_reference', 64)->nullable()->after('reference');
            $table->index('checkout_reference');
        });
    }

    public function down(): void
    {
        Schema::table('product_sales', function (Blueprint $table) {
            $table->dropIndex(['checkout_reference']);
            $table->dropColumn(['quantity', 'checkout_reference']);
        });
    }
};
