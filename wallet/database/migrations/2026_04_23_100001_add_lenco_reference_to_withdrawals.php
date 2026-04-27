<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            if (! Schema::hasColumn('withdrawals', 'lenco_reference')) {
                $table->string('lenco_reference')->nullable()->index()->after('reference');
            }
            if (! Schema::hasColumn('withdrawals', 'provider')) {
                $table->string('provider')->nullable()->after('currency');
            }
            if (! Schema::hasColumn('withdrawals', 'account_number')) {
                $table->string('account_number')->nullable()->after('phone_number');
            }
            if (! Schema::hasColumn('withdrawals', 'account_name')) {
                $table->string('account_name')->nullable()->after('account_number');
            }
            if (! Schema::hasColumn('withdrawals', 'bank_code')) {
                $table->string('bank_code')->nullable()->after('account_name');
            }
            if (! Schema::hasColumn('withdrawals', 'narration')) {
                $table->string('narration')->nullable()->after('bank_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn([
                'lenco_reference',
                'provider',
                'account_number',
                'account_name',
                'bank_code',
                'narration',
            ]);
        });
    }
};
