<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Upgrade wallets table ───────────────────────────────────────────
        Schema::table('wallets', function (Blueprint $table) {
            $table->decimal('pending_balance', 15, 2)->default(0)->after('balance');
            $table->decimal('lifetime_cashback_earned', 15, 2)->default(0)->after('pending_balance');
            $table->decimal('lifetime_cashback_spent', 15, 2)->default(0)->after('lifetime_cashback_earned');
            $table->decimal('lifetime_withdrawn', 15, 2)->default(0)->after('lifetime_cashback_spent');
            $table->string('card_number', 20)->nullable()->after('currency');
            $table->string('expiry', 7)->nullable()->after('card_number');
        });

        // ── Rename balance to available_balance ─────────────────────────────
        Schema::table('wallets', function (Blueprint $table) {
            $table->renameColumn('balance', 'available_balance');
        });

        // ── wallet_ledgers ──────────────────────────────────────────────────
        Schema::create('wallet_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);              // cashback_credit_pending, withdrawal_debit, etc.
            $table->enum('direction', ['credit', 'debit']);
            $table->decimal('amount', 15, 2);
            $table->string('reference_type', 50)->nullable();   // payment, cashback, withdrawal, admin
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        // ── merchants ───────────────────────────────────────────────────────
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('category', 50)->nullable();
            $table->boolean('cashback_eligible')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // ── orders ──────────────────────────────────────────────────────────
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_reference')->unique();
            $table->decimal('gross_amount', 15, 2);
            $table->decimal('eligible_amount', 15, 2);  // gross minus fees/taxes
            $table->decimal('fee_amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('ZMW');
            $table->string('description')->nullable();
            $table->string('status', 20)->default('pending');  // pending, paid, cancelled, refunded
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'created_at']);
        });

        // ── payments (upgrade existing transactions table intent) ───────────
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_reference')->unique();
            $table->string('provider_reference')->nullable()->unique();
            $table->string('payment_method', 30)->default('mobile_money');
            $table->decimal('amount', 15, 2);
            $table->decimal('eligible_amount', 15, 2);
            $table->string('currency', 3)->default('ZMW');
            $table->string('phone_number', 20)->nullable();
            $table->string('status', 20)->default('initiated');
            // initiated | pending | processing | successful | failed | reversed | cancelled
            $table->json('gateway_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index('payment_reference');
        });

        // ── cashback_transactions ───────────────────────────────────────────
        Schema::create('cashback_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->decimal('payment_amount', 15, 2);
            $table->decimal('cashback_amount', 15, 2);
            $table->decimal('cashback_rate', 5, 4)->default(0.02);  // 2%
            $table->string('status', 20)->default('pending');
            // pending | locked | available | reversed | expired
            $table->timestamp('hold_until')->nullable();    // release after this date
            $table->timestamp('released_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('payment_id');   // one cashback per payment (idempotency)
            $table->index(['user_id', 'status']);
            $table->index('hold_until');
        });

        // ── kyc_records ─────────────────────────────────────────────────────
        Schema::create('kyc_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('id_type', 30);      // national_id, passport, driver_licence
            $table->string('id_number', 50);
            $table->string('id_document_path')->nullable();
            $table->string('selfie_path')->nullable();
            $table->string('status', 20)->default('pending');
            // not_submitted | pending | verified | rejected | expired
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ── withdrawals ─────────────────────────────────────────────────────
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('ZMW');
            $table->string('phone_number', 20);
            $table->string('status', 20)->default('requested');
            // requested | under_review | approved | processing | paid | rejected | failed | reversed
            $table->string('provider_reference')->nullable();
            $table->json('provider_payload')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });

        // ── fraud_flags ─────────────────────────────────────────────────────
        Schema::create('fraud_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('flag_type', 50);  // duplicate_account, velocity, refund_abuse, etc.
            $table->string('status', 20)->default('flagged');
            // clear | flagged | under_review | blocked | resolved
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // ── notifications ───────────────────────────────────────────────────
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);     // payment_success, cashback_earned, etc.
            $table->string('title');
            $table->text('body');
            $table->string('channel', 20)->default('in_app'); // in_app | sms | email | push
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_read']);
        });

        // ── admin_adjustments ───────────────────────────────────────────────
        Schema::create('admin_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->enum('direction', ['credit', 'debit']);
            $table->decimal('amount', 15, 2);
            $table->string('reason');
            $table->timestamps();
        });

        // ── system_settings ─────────────────────────────────────────────────
        if (! Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->string('description')->nullable();
                $table->timestamps();
            });
        }

        // ── audit_logs ───────────────────────────────────────────────────────
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100);
            $table->string('auditable_type', 100)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('admin_adjustments');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('fraud_flags');
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('kyc_records');
        Schema::dropIfExists('cashback_transactions');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('merchants');
        Schema::dropIfExists('wallet_ledgers');

        Schema::table('wallets', function (Blueprint $table) {
            $table->renameColumn('available_balance', 'balance');
            $table->dropColumn([
                'pending_balance',
                'lifetime_cashback_earned',
                'lifetime_cashback_spent',
                'lifetime_withdrawn',
                'card_number',
                'expiry',
            ]);
        });
    }
};
