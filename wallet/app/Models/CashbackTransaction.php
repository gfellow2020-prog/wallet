<?php

namespace App\Models;

use App\Enums\CashbackStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashbackTransaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'wallet_id', 'payment_id',
        'cashback_amount', 'cashback_rate', 'status',
        'hold_until', 'released_at', 'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'cashback_amount' => 'decimal:2',
            'cashback_rate' => 'decimal:4',
            'status' => CashbackStatus::class,
            'hold_until' => 'datetime',
            'released_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
