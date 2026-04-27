<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('user_notifications', 'dedupe_key')) {
                $table->string('dedupe_key', 120)->nullable()->after('type');
            }
            if (! Schema::hasColumn('user_notifications', 'is_sensitive')) {
                $table->boolean('is_sensitive')->default(false)->after('channel');
            }

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'is_read', 'created_at']);
            $table->index(['user_id', 'dedupe_key']);
        });

        Schema::create('user_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('push_enabled')->default(true);
            $table->boolean('email_enabled')->default(false);
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('hide_sensitive_push')->default(true);
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->boolean('push_enabled')->nullable();
            $table->boolean('email_enabled')->nullable();
            $table->boolean('in_app_enabled')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'type']);
        });

        Schema::create('expo_push_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_push_token_id')->nullable();
            $table->string('expo_token', 255);
            $table->unsignedBigInteger('user_notification_id')->nullable();
            $table->string('ticket_id', 120)->nullable();
            $table->string('status', 20)->default('pending'); // pending|ok|error
            $table->string('error', 80)->nullable();
            $table->json('details')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'checked_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['expo_token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expo_push_tickets');
        Schema::dropIfExists('user_notification_preferences');
        Schema::dropIfExists('user_notification_settings');

        Schema::table('user_notifications', function (Blueprint $table) {
            // indexes will be dropped implicitly in many DBs; keep down minimal.
            if (Schema::hasColumn('user_notifications', 'dedupe_key')) {
                $table->dropColumn('dedupe_key');
            }
            if (Schema::hasColumn('user_notifications', 'is_sensitive')) {
                $table->dropColumn('is_sensitive');
            }
        });
    }
};

