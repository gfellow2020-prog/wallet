<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nrc_number', 30)->nullable()->after('email')->index();
            $table->string('tpin', 30)->nullable()->after('nrc_number')->index();
            $table->string('profile_photo_path')->nullable()->after('tpin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nrc_number', 'tpin', 'profile_photo_path']);
        });
    }
};
