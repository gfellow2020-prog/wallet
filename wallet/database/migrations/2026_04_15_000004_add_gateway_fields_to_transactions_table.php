<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('gateway_reference')->nullable()->after('narration');
            $table->string('gateway_status', 30)->default('local')->after('gateway_reference'); // local | pending | success | failed
            $table->string('phone_number', 20)->nullable()->after('gateway_status');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['gateway_reference', 'gateway_status', 'phone_number']);
        });
    }
};
