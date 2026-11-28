<?php

namespace Modules\People\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\People\Enums\CustomerStatus;

final class Customer extends Model
{
    protected $fillable = [
        'name',
        'mobile',
    ];

    protected $attributes = ['is_active' => CustomerStatus::ACTIVE->value];

    protected function casts(): array
    {
        return [
            'is_active' => CustomerStatus::class,
        ];
    }
}
