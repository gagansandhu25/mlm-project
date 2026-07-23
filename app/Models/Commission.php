<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'from_user_id', 'order_id', 'plan_type', 'base_amount', 'amount',
    'percentage', 'rank_multiplier', 'level', 'position', 'status', 'units_matched',
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

    // Distinct plan_type values so a plan's per-period cap
    // (LevelLadderPayer::periodCommissionSum, filtered by plan_type
    // only, no level filter) never absorbs an income module's payouts,
    // even when both pay the same upline from the same order.
    const TYPE_DIRECT_REFERRAL_BONUS = 'direct_referral_bonus';

    const TYPE_MULTI_TIER_REFERRAL_BONUS = 'multi_tier_referral_bonus';

    const TYPE_SIDELINE_GROWTH_BONUS = 'sideline_growth_bonus';

    // Kept distinct from each other (self payout vs. pool payout) for
    // the same reason — a future cap on one must never absorb the other.
    const TYPE_HYBRID_BINARY_MATCHING = 'hybrid_binary_matching';

    const TYPE_HYBRID_BINARY_POOL = 'hybrid_binary_pool';

    const TYPE_CONFIGURABLE_BINARY_MATCHING = 'configurable_binary_matching';

    const TYPE_CONFIGURABLE_BINARY_POOL = 'configurable_binary_pool';

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'percentage' => 'decimal:2',
            'rank_multiplier' => 'decimal:2',
            'level' => 'integer',
            'units_matched' => 'decimal:4',
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
