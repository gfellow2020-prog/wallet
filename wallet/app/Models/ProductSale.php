<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSale extends Model
{
    protected $fillable = [
        'product_id',
        'buyer_id',
        'seller_id',
        'quantity',
        'gross_amount',
        'admin_fee',
        'cashback_amount',
        'seller_net',
        'status',
        'reference',
        'checkout_reference',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'gross_amount' => 'float',
        'admin_fee' => 'float',
        'cashback_amount' => 'float',
        'seller_net' => 'float',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
