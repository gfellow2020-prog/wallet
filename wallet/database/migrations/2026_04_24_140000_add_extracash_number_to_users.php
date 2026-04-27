<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a short, unique, human-typeable identifier to every user — the
 * "ExtraCash Number". It's used for:
 *
 *   - Looking up other users when minting a directed Buy-for-Me request
 *   - Lightweight peer discovery without exposing email / phone / NRC
 *
 * We backfill existing rows in-migration so the column can be marked
 * NOT NULL + UNIQUE in a single migration pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nullable first so we can safely backfill before adding the
            // uniqueness / NOT NULL constraints.
            $table->string('extracash_number', 16)->nullable()->after('id');
        });

        // Backfill — keep retrying per user to guarantee uniqueness even
        // under a tiny random-collision window.
        User::whereNull('extracash_number')->orderBy('id')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                $user->extracash_number = self::mintUniqueNumber();
                $user->save();
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('extracash_number', 16)->nullable(false)->change();
            $table->unique('extracash_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['extracash_number']);
            $table->dropColumn('extracash_number');
        });
    }

    private static function mintUniqueNumber(): string
    {
        // 8-digit numeric handle. Short enough to type, long enough that
        // collisions are rare. We loop defensively in case of a rare clash.
        do {
            $candidate = (string) random_int(10_000_000, 99_999_999);
        } while (DB::table('users')->where('extracash_number', $candidate)->exists());

        return $candidate;
    }
};
