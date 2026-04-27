<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuyRequest extends Model
{
    protected $fillable = [
        'token',
        'product_id',
        'requester_id',
        'target_user_id',
        'fulfilled_by',
        'product_sale_id',
        'status',
        'note',
        'expires_at',
        'fulfilled_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'fulfilled_at' => 'datetime',
    ];

    protected $appends = ['qr_payload', 'is_expired'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function fulfiller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfilled_by');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(ProductSale::class, 'product_sale_id');
    }

    /**
     * Base64-encoded JSON payload scanned in the mobile app.
     *
     * Uses the same shape as product/payment QRs so the shared scanner can
     * branch on the `t` discriminator:
     *   base64( json( { t: 'buyfor', token: <uuid>, v: 1 } ) )
     */
    public function getQrPayloadAttribute(): string
    {
        return base64_encode(json_encode([
            't' => 'buyfor',
            'token' => (string) $this->token,
            'v' => 1,
        ]));
    }

    /**
     * True once the request's expiry has passed. Used in serializers to avoid
     * leaking "payable" affordances for stale links.
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at ? $this->expires_at->isPast() : false;
    }

    /**
     * Only `pending` requests that haven't passed their expiry are payable.
     */
    public function isPayable(): bool
    {
        return $this->status === 'pending' && ! $this->is_expired;
    }
}
