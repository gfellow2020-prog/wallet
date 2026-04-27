<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buy_requests', function (Blueprint $table) {
            $table->id();
            // Public, unguessable token used in QR payloads and share links.
            $table->uuid('token')->unique();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            // The user asking for a sponsor to pay on their behalf.
            $table->foreignId('requester_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // The sponsor who eventually paid (null while pending).
            $table->foreignId('fulfilled_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Resulting sale once paid (null while pending).
            $table->foreignId('product_sale_id')
                ->nullable()
                ->constrained('product_sales')
                ->nullOnDelete();

            // State machine: pending → fulfilled | cancelled | expired
            $table->string('status', 20)->default('pending');

            // Optional message from requester to sponsor ("please get me this").
            $table->text('note')->nullable();

            // Short-lived tokens reduce blast radius of link sharing.
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();

            $table->timestamps();

            $table->index(['requester_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buy_requests');
    }
};
