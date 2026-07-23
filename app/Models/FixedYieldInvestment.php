<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'invested_amount', 'invested_at', 'status'])]
class FixedYieldInvestment extends Model
{
    use HasFactory;

    const STATUS_ACTIVE = 'active';

    const STATUS_CAPPED_OUT = 'capped_out';

    const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'invested_amount' => 'decimal:2',
            'invested_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dailyAccruals(): HasMany
    {
        return $this->hasMany(FixedYieldDailyAccrual::class, 'investment_id');
    }
}
