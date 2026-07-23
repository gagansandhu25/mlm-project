<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'investment_id', 'accrued_on', 'monthly_rate',
    'base_amount', 'amount', 'status', 'paid_at', 'description',
])]
class FixedYieldDailyAccrual extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';

    const STATUS_PAID = 'paid';

    const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'accrued_on' => 'date',
            'monthly_rate' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(FixedYieldInvestment::class, 'investment_id');
    }
}
