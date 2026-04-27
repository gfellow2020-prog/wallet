<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name',
    'email',
    'password',
    'phone_number',
    'phone_verified_at',
    'nrc_number',
    'tpin',
    'profile_photo_path',
    'extracash_number',
    'last_login_ip',
    'last_login_device_id',
    'last_login_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Auto-mint an ExtraCash Number for any new user that doesn't have one.
     * Runs at the model boot layer so every entry-point (register flow,
     * tinker, seeders, admin Nova forms, etc.) gets a number without each
     * needing to remember to set one.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->extracash_number)) {
                $user->extracash_number = static::mintUniqueExtracashNumber();
            }
        });
    }

    /**
     * Generate an 8-digit numeric handle guaranteed unique in the users
     * table at call time. The loop is a defensive guard against the very
     * unlikely random collision.
     */
    public static function mintUniqueExtracashNumber(): string
    {
        do {
            $candidate = (string) random_int(10_000_000, 99_999_999);
        } while (DB::table('users')->where('extracash_number', $candidate)->exists());

        return $candidate;
    }

    /**
     * Strip everything except digits. Lets a user paste "EC 1234 5678" or
     * "ec-12345678" and still resolve to the same record.
     */
    public static function normalizeExtracashNumber(string $raw): string
    {
        return preg_replace('/\D+/', '', $raw) ?? '';
    }

    /**
     * Temporary admin check backed by a config allowlist until the project
     * grows a dedicated roles/permissions model.
     */
    public function isAdmin(): bool
    {
        $allowedEmails = config('admin.emails', []);

        if (! is_array($allowedEmails) || empty($allowedEmails)) {
            return false;
        }

        return in_array(strtolower((string) $this->email), $allowedEmails, true);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function cashbacks(): HasMany
    {
        return $this->hasMany(CashbackTransaction::class);
    }

    // Backward-compatible alias used by some admin controllers.
    public function cashbackTransactions(): HasMany
    {
        return $this->cashbacks();
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public function kycRecord(): HasOne
    {
        return $this->hasOne(KycRecord::class);
    }

    // Admin views/controllers expect a collection and call ->last().
    public function kycRecords(): HasMany
    {
        return $this->hasMany(KycRecord::class);
    }

    public function fraudFlags(): HasMany
    {
        return $this->hasMany(FraudFlag::class);
    }

    public function hasOpenFraudFlags(): bool
    {
        return $this->fraudFlags()->whereNull('resolved_at')->exists();
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(WalletLedger::class);
    }

    public function streaks(): HasMany
    {
        return $this->hasMany(UserStreak::class);
    }

    public function missions(): HasMany
    {
        return $this->hasMany(UserMission::class);
    }

    public function rewardGrants(): HasMany
    {
        return $this->hasMany(RewardGrant::class);
    }

    public function pushTokens(): HasMany
    {
        return $this->hasMany(UserPushToken::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    public function adminNotes(): HasMany
    {
        return $this->hasMany(UserAdminNote::class);
    }

    public function conversationParticipants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(UserBlock::class, 'blocker_id');
    }

    public function notificationSettings(): HasOne
    {
        return $this->hasOne(UserNotificationSetting::class);
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(UserNotificationPreference::class);
    }
}
