<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable()->after('last_login_at');
            }
            if (! Schema::hasColumn('users', 'suspended_by')) {
                $table->foreignId('suspended_by')->nullable()->after('suspended_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'suspension_reason')) {
                $table->text('suspension_reason')->nullable()->after('suspended_by');
            }
        });

        if (! Schema::hasTable('user_admin_notes')) {
            Schema::create('user_admin_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
                $table->text('note');
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_admin_notes')) {
            Schema::dropIfExists('user_admin_notes');
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'suspension_reason')) {
                $table->dropColumn('suspension_reason');
            }
            if (Schema::hasColumn('users', 'suspended_by')) {
                $table->dropConstrainedForeignId('suspended_by');
            }
            if (Schema::hasColumn('users', 'suspended_at')) {
                $table->dropColumn('suspended_at');
            }
        });
    }
};

