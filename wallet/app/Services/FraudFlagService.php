<?php

namespace App\Services;

use App\Models\FraudFlag;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\Log;

class FraudFlagService
{
    public function __construct(
        protected NotificationService $notifications,
    ) {}

    /**
     * Create an open fraud flag if there is not already an unresolved one of the same type for this user.
     */
    public function flagIfNew(User $user, string $flagType, string $notes, string $status = 'flagged'): ?FraudFlag
    {
        $exists = FraudFlag::query()
            ->where('user_id', $user->id)
            ->where('flag_type', $flagType)
            ->whereNull('resolved_at')
            ->exists();

        if ($exists) {
            return null;
        }

        $flag = FraudFlag::create([
            'user_id' => $user->id,
            'flag_type' => $flagType,
            'status' => $status,
            'notes' => $notes,
        ]);

        Log::info('fraud_flag.created', [
            'flag_id' => $flag->id,
            'user_id' => $user->id,
            'flag_type' => $flagType,
        ]);

        $this->notifications->notifyAdmins(
            'admin_fraud_flag',
            'Fraud flag created',
            sprintf('User #%d flagged (%s).', (int) $user->id, $flagType),
            ['flag_id' => $flag->id, 'user_id' => $user->id, 'flag_type' => $flagType],
            sendEmail: true,
        );

        return $flag;
    }

    /**
     * After a mobile-money send: flag if many "Sent to" debits in the last hour.
     */
    public function checkSendVelocity(User $user, int $walletId): void
    {
        $threshold = 5;
        $since = now()->subHour();

        $count = Transaction::query()
            ->where('wallet_id', $walletId)
            ->where('type', 'debit')
            ->where('narration', 'like', 'Sent to%')
            ->where('transacted_at', '>=', $since)
            ->count();

        if ($count < $threshold) {
            return;
        }

        $this->flagIfNew(
            $user,
            'send_velocity',
            sprintf('User completed %d wallet sends in the last hour (threshold %d).', $count, $threshold)
        );
    }

    /**
     * After a withdrawal request: flag if many withdrawals in the last 24 hours.
     */
    public function checkWithdrawalVelocity(User $user): void
    {
        $threshold = 4;
        $count = Withdrawal::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($count < $threshold) {
            return;
        }

        $this->flagIfNew(
            $user,
            'withdrawal_velocity',
            sprintf('User requested %d withdrawals in the last 24 hours (threshold %d).', $count, $threshold)
        );
    }
}
