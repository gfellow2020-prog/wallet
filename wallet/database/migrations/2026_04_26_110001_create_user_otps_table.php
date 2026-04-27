<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 30); // login|send_money
            $table->string('channel', 20); // sms|email
            $table->string('destination', 190); // phone or email
            $table->string('code_hash', 255);
            $table->unsignedSmallInteger('attempts')->default(0);
            // Use datetime for broad MySQL compatibility (avoids TIMESTAMP default constraints).
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('expires_at');
            $table->dateTime('verified_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'purpose', 'verified_at']);
            $table->index(['expires_at']);
            $table->index(['purpose', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_otps');
    }
};

