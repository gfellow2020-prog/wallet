<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'category',
        'price',
        'cashback_amount',
        'cashback_rate',
        'image_url',
        'condition',
        'stock',
        'is_active',
        'latitude',
        'longitude',
        'location_label',
        'clicks',
    ];

    protected $casts = [
        'price' => 'float',
        'cashback_amount' => 'float',
        'cashback_rate' => 'float',
        'latitude' => 'float',
        'longitude' => 'float',
        'is_active' => 'boolean',
        'stock' => 'integer',
        'clicks' => 'integer',
    ];

    /**
     * Always include `qr_payload` when a Product is serialized so any screen
     * that needs to display or print the product QR can just read it off the
     * JSON response.
     */
    // NOTE: `qr_payload` is intentionally NOT auto-appended to reduce payload size on listings.
    // Endpoints that need it should explicitly append or expose it via a Resource/DTO.
    protected $appends = [];

    /**
     * Stable, compact QR payload that the mobile scanner can decode.
     *
     * Format: base64( json( { t: 'product', pid, v: 1 } ) )
     *  - `t` mirrors the user-payment QR discriminator (`t: 'payment'`) so
     *    one scanner can route both kinds of codes.
     *  - `pid` is the product id (server re-validates existence/stock).
     *  - `v` leaves room for future schema upgrades.
     *
     * No signing is required: product ids are public, and the server enforces
     * every security rule (active, in-stock, not-own-listing) on add-to-cart
     * and checkout regardless of how the id was produced.
     */
    public function getQrPayloadAttribute(): string
    {
        return base64_encode(json_encode([
            't' => 'product',
            'pid' => (int) $this->id,
            'v' => 1,
        ]));
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ProductComment::class)->latest();
    }

    public function likes(): HasMany
    {
        return $this->hasMany(ProductLike::class);
    }

    /**
     * Compute distance in km from a given lat/lng (Haversine formula, pure SQL).
     * Usage: Product::nearby($lat, $lng, 25)->get()
     */
    public function scopeNearby($query, float $lat, float $lng, float $radiusKm = 25)
    {
        $haversine = "( 6371 * acos( cos( radians({$lat}) ) * cos( radians(latitude) ) * cos( radians(longitude) - radians({$lng}) ) + sin( radians({$lat}) ) * sin( radians(latitude) ) ) )";

        return $query
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereRaw("{$haversine} <= ?", [$radiusKm])
            ->selectRaw("*, {$haversine} AS distance_km")
            ->orderByDesc('clicks')        // most clicked first
            ->orderByRaw($haversine);      // then by distance
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('stock', '>', 0);
    }
}
