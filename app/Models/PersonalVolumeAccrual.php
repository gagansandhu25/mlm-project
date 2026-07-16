<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'accrued_on', 'sales_volume_snapshot', 'percentage',
    'rank_multiplier', 'base_amount', 'amount', 'status', 'paid_at', 'description',
])]
class PersonalVolumeAccrual extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';

    const STATUS_PAID = 'paid';

    const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'accrued_on' => 'date',
            'sales_volume_snapshot' => 'decimal:2',
            'percentage' => 'decimal:2',
            'rank_multiplier' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
