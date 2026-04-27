<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('streak_type', 50);
            $table->unsignedInteger('current_count')->default(0);
            $table->unsignedInteger('longest_count')->default(0);
            $table->date('last_qualified_on')->nullable();
            $table->date('last_claimed_on')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'streak_type']);
        });

        Schema::create('mission_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 100)->unique();
            $table->string('title', 120);
            $table->string('description')->nullable();
            $table->string('action_type', 50);
            $table->unsignedInteger('target_count')->default(1);
            $table->string('reward_type', 50);
            $table->string('reward_value', 120)->nullable();
            $table->json('reward_meta')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'action_type']);
        });

        Schema::create('user_missions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mission_definition_id')->constrained()->cascadeOnDelete();
            $table->date('period_date');
            $table->unsignedInteger('progress')->default(0);
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_claimed')->default(false);
            $table->timestamp('claimed_at')->nullable();
            $table->json('source_meta')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'mission_definition_id', 'period_date'], 'user_missions_daily_unique');
            $table->index(['user_id', 'period_date']);
        });

        Schema::create('reward_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mission_definition_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_mission_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reward_type', 50);
            $table->string('reward_value', 120)->nullable();
            $table->string('status', 30)->default('granted');
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_grants');
        Schema::dropIfExists('user_missions');
        Schema::dropIfExists('mission_definitions');
        Schema::dropIfExists('user_streaks');
    }
};
