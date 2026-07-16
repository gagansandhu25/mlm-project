<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'level', 'icon', 'min_sales_volume', 'min_downline',
    'commission_multiplier', 'rank_commission_rate', 'is_active',
])]
class Rank extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'min_sales_volume' => 'decimal:2',
            'commission_multiplier' => 'decimal:2',
            'rank_commission_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
