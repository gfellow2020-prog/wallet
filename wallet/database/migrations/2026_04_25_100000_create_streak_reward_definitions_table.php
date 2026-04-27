<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('streak_reward_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('day_number');
            $table->string('code', 100)->unique();
            $table->string('title', 120);
            $table->string('description')->nullable();
            $table->string('reward_type', 50);
            $table->string('reward_value', 120)->nullable();
            $table->json('reward_meta')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('day_number');
            $table->index(['is_active', 'day_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streak_reward_definitions');
    }
};
