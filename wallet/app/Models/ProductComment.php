<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductComment extends Model
{
    protected $fillable = ['product_id', 'user_id', 'body'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->select(['id', 'name']);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
