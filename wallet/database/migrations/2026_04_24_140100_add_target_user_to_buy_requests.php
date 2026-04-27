<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `target_user_id` makes a Buy-for-Me request *directed* at a specific
 * sponsor. When set, only that user can fulfil it (enforced in
 * BuyRequestService). When null, the request is open and any sponsor
 * with the token / QR can fulfil it (original behaviour).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buy_requests', function (Blueprint $table) {
            $table->foreignId('target_user_id')
                ->nullable()
                ->after('requester_id')
                ->constrained('users')
                ->nullOnDelete();

            // Used by the inbox query ("show me pending requests aimed at me").
            $table->index(['target_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('buy_requests', function (Blueprint $table) {
            $table->dropIndex(['target_user_id', 'status']);
            $table->dropForeign(['target_user_id']);
            $table->dropColumn('target_user_id');
        });
    }
};
