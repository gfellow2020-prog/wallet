<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'order_id', 'payment_reference', 'provider_reference',
        'amount', 'eligible_amount', 'currency', 'status',
        'gateway_payload', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'eligible_amount' => 'decimal:2',
            'status' => PaymentStatus::class,
            'gateway_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Partner merchant (via order), for admin lists and `with('merchant')`.
     */
    public function merchant(): HasOneThrough
    {
        return $this->hasOneThrough(
            Merchant::class,
            Order::class,
            'id',
            'id',
            'order_id',
            'merchant_id'
        );
    }

    public function cashback(): HasOne
    {
        return $this->hasOne(CashbackTransaction::class);
    }
}
