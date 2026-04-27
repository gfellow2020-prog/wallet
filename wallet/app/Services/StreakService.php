<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserStreak;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StreakService
{
    public const DAILY_CHECK_IN = 'daily_check_in';

    /**
     * Record the user's once-per-day streak check-in.
     *
     * @return array{streak: UserStreak, checked_in: bool, continued: bool, reset: bool}
     */
    public function checkIn(User $user, string $streakType = self::DAILY_CHECK_IN, ?Carbon $today = null): array
    {
        $today = ($today ?? now())->copy()->startOfDay();

        return DB::transaction(function () use ($user, $streakType, $today) {
            $streak = UserStreak::query()
                ->where('user_id', $user->id)
                ->where('streak_type', $streakType)
                ->lockForUpdate()
                ->first();

            if (! $streak) {
                $streak = UserStreak::create([
                    'user_id' => $user->id,
                    'streak_type' => $streakType,
                    'current_count' => 1,
                    'longest_count' => 1,
                    'last_qualified_on' => $today,
                ]);

                return [
                    'streak' => $streak,
                    'checked_in' => true,
                    'continued' => false,
                    'reset' => false,
                ];
            }

            $lastQualified = $streak->last_qualified_on
                ? Carbon::parse($streak->last_qualified_on)->startOfDay()
                : null;
            if ($lastQualified && $lastQualified->equalTo($today)) {
                return [
                    'streak' => $streak->fresh(),
                    'checked_in' => false,
                    'continued' => false,
                    'reset' => false,
                ];
            }

            $yesterday = $today->copy()->subDay();
            $continued = $lastQualified && $lastQualified->equalTo($yesterday);
            $newCount = $continued ? ((int) $streak->current_count + 1) : 1;

            $streak->update([
                'current_count' => $newCount,
                'longest_count' => max((int) $streak->longest_count, $newCount),
                'last_qualified_on' => $today,
            ]);

            return [
                'streak' => $streak->fresh(),
                'checked_in' => true,
                'continued' => $continued,
                'reset' => ! $continued && $lastQualified !== null,
            ];
        });
    }

    public function summary(User $user, string $streakType = self::DAILY_CHECK_IN): array
    {
        $streak = UserStreak::query()
            ->firstOrCreate(
                ['user_id' => $user->id, 'streak_type' => $streakType],
                ['current_count' => 0, 'longest_count' => 0]
            );

        return [
            'type' => $streak->streak_type,
            'current_count' => (int) $streak->current_count,
            'longest_count' => (int) $streak->longest_count,
            'last_qualified_on' => $streak->last_qualified_on
                ? Carbon::parse($streak->last_qualified_on)->toDateString()
                : null,
        ];
    }
}
