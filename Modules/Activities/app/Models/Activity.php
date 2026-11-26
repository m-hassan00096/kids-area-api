<?php

namespace Modules\Activities\Models;

use Illuminate\Database\Eloquent\Model;

final class Activity extends Model
{
    protected $fillable = [
        'name',
        'supports_hourly',
        'supports_subscription',
        'hourly_rate',
        'monthly_price',
        'is_active',
    ];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'supports_hourly' => 'boolean',
            'supports_subscription' => 'boolean',
            'hourly_rate' => 'decimal:2',
            'monthly_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
