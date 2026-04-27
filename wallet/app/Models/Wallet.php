<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $fillable = [
        'user_id', 'available_balance', 'pending_balance', 'currency',
        'lifetime_cashback_earned', 'lifetime_cashback_spent', 'lifetime_withdrawn',
        'card_number', 'expiry',
    ];

    protected function casts(): array
    {
        return [
            'available_balance' => 'decimal:2',
            'pending_balance' => 'decimal:2',
            'lifetime_cashback_earned' => 'decimal:2',
            'lifetime_cashback_spent' => 'decimal:2',
            'lifetime_withdrawn' => 'decimal:2',
        ];
    }

    /** Total spendable balance */
    public function getTotalBalanceAttribute(): float
    {
        return (float) $this->available_balance + (float) $this->pending_balance;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class)->latest('transacted_at');
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(WalletLedger::class)->latest();
    }

    public function cashbacks(): HasMany
    {
        return $this->hasMany(CashbackTransaction::class)->latest();
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class)->latest();
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class)->latest();
    }
}
