<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'from_user_id', 'order_id', 'plan_type', 'base_amount', 'amount',
    'percentage', 'rank_multiplier', 'level', 'position', 'status',
    'description', 'calculated_at', 'paid_at',
])]
class Commission extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';

    const STATUS_PAID = 'paid';

    const STATUS_CANCELLED = 'cancelled';

    const TYPE_UNILEVEL = 'unilevel';

    const TYPE_BINARY = 'binary';

    const TYPE_MATRIX = 'matrix';

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'percentage' => 'decimal:2',
            'rank_multiplier' => 'decimal:2',
            'level' => 'integer',
            'calculated_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
