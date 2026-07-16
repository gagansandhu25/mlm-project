<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'description', 'price', 'commission_value', 'image',
    'stock', 'category', 'status',
])]
class Product extends Model
{
    use HasFactory;

    const STATUS_ACTIVE = 'active';

    const STATUS_INACTIVE = 'inactive';

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'commission_value' => 'decimal:2',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
