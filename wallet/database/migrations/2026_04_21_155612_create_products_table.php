<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // seller
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('cashback_amount', 12, 2)->default(0); // fixed cashback for buyer
            $table->decimal('cashback_rate', 5, 4)->default(0.02); // rate used to compute cashback
            $table->string('image_url')->nullable();
            $table->string('condition')->default('new'); // new|used|refurbished
            $table->integer('stock')->default(1);
            $table->boolean('is_active')->default(true);
            // Location
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('location_label')->nullable(); // e.g. "Lusaka, Woodlands"
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
