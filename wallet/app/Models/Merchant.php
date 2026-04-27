<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Merchant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'code', 'category', 'cashback_rate',
        'cashback_eligible', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cashback_rate' => 'decimal:4',
            'cashback_eligible' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Short unique code for API/payments (A–Z, 0–9), derived from the name.
     */
    public static function makeUniqueCodeFromName(string $name): string
    {
        $slug = strtoupper(preg_replace('/[^A-Z0-9]+/', '', Str::ascii(Str::slug($name, ''))));

        if ($slug === '' || $slug === 'M') {
            $slug = 'MCH';
        }

        $slug = substr($slug, 0, 16);
        $code = $slug;
        $i = 0;

        while (static::withTrashed()->where('code', $code)->exists()) {
            $i++;
            $suffix = (string) $i;
            $code = strtoupper(substr($slug, 0, 20 - strlen($suffix)).$suffix);
        }

        return $code;
    }
}
