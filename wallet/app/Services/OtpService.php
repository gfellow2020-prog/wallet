<?php

namespace App\Services;

use App\Mail\GenericNotificationMail;
use App\Models\User;
use App\Models\UserOtp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OtpService
{
    public function __construct(
        protected SmsService $sms,
    ) {}

    public function otpExpiryMinutes(): int
    {
        return 5;
    }

    public function maxAttempts(): int
    {
        return 5;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{otp: UserOtp, sent_via: string, fallback_used: bool}
     */
    public function createAndSend(User $user, string $purpose, array $context = []): array
    {
        // Revoke any existing active challenges for same purpose.
        UserOtp::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $code = app()->environment('testing')
            ? '123456'
            : (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes($this->otpExpiryMinutes());

        [$channel, $destination, $fallback] = $this->chooseDestination($user);

        $otp = UserOtp::query()->create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'channel' => $channel,
            'destination' => $destination,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'sent_at' => null,
            'expires_at' => $expiresAt,
            'verified_at' => null,
            'revoked_at' => null,
            'context' => $context,
        ]);

        $message = $this->renderOtpMessage($code, $purpose);

        $sentVia = 'none';
        $fallbackUsed = false;

        if ($channel === 'sms') {
            $sms = $this->sms->send($destination, $message);
            if ($sms['ok']) {
                $sentVia = 'sms';
            } else {
                $fallbackUsed = true;
                $sentVia = $this->sendEmailFallback($user, $code, $purpose) ? 'email' : 'none';
            }
        } else {
            $sentVia = $this->sendEmailFallback($user, $code, $purpose) ? 'email' : 'none';
        }

        $otp->update([
            'sent_at' => now(),
            'channel' => $sentVia === 'email' ? 'email' : $otp->channel,
            'destination' => $sentVia === 'email' ? (string) $user->email : $otp->destination,
        ]);

        if ($sentVia === 'none') {
            Log::warning('otp.send.failed', [
                'user_id' => $user->id,
                'purpose' => $purpose,
            ]);
        }

        return [
            'otp' => $otp->fresh(),
            'sent_via' => $sentVia,
            'fallback_used' => $fallbackUsed || $fallback,
        ];
    }

    public function verify(User $user, int $otpId, string $purpose, string $code): bool
    {
        /** @var UserOtp|null $otp */
        $otp = UserOtp::query()
            ->where('id', $otpId)
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->first();

        if (! $otp) {
            return false;
        }

        if ($otp->verified_at !== null || $otp->revoked_at !== null) {
            return false;
        }

        if ($otp->expires_at->isPast()) {
            $otp->update(['revoked_at' => now()]);
            return false;
        }

        if ((int) $otp->attempts >= $this->maxAttempts()) {
            $otp->update(['revoked_at' => now()]);
            return false;
        }

        $otp->increment('attempts');

        if (! Hash::check($code, (string) $otp->code_hash)) {
            return false;
        }

        $otp->update([
            'verified_at' => now(),
        ]);

        return true;
    }

    /**
     * @return array{0: 'sms'|'email', 1: string, 2: bool}
     */
    private function chooseDestination(User $user): array
    {
        $phone = is_string($user->phone_number ?? null) ? trim((string) $user->phone_number) : '';
        if ($phone !== '') {
            return ['sms', $this->normalizeZambiaPhone($phone) ?? $phone, false];
        }

        return ['email', (string) $user->email, true];
    }

    private function normalizeZambiaPhone(string $raw): ?string
    {
        $digits = preg_replace('/\\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '260') && strlen($digits) === 12) {
            return $digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '260'.substr($digits, 1);
        }
        if (strlen($digits) === 9) {
            return '260'.$digits;
        }

        return null;
    }

    private function renderOtpMessage(string $code, string $purpose): string
    {
        // Keep the message AutoFill-friendly (especially iOS): put the code early,
        // and avoid extra numbers (like "expires in 5 minutes") that can confuse parsers.
        $app = config('app.name', 'App');
        $label = match ($purpose) {
            'login' => 'sign in',
            'send_money' => 'transfer',
            'phone_verify' => 'verify your number',
            default => $purpose,
        };

        return sprintf(
            '%s code: %s. For %s. Do not share this code.',
            $app,
            $code,
            $label
        );
    }

    private function sendEmailFallback(User $user, string $code, string $purpose): bool
    {
        if (! is_string($user->email ?? null) || trim((string) $user->email) === '') {
            return false;
        }

        try {
            Mail::to($user->email)->queue(new GenericNotificationMail([
                'title' => 'Your OTP code',
                'body' => $this->renderOtpMessage($code, $purpose),
            ]));

            return true;
        } catch (\Throwable $e) {
            Log::error('otp.email.failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}

