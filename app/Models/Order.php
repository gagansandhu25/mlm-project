<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'product_id', 'order_number', 'amount', 'commission_value',
    'status', 'order_date', 'payment_method', 'payment_status', 'commission_processed',
])]
class Order extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';

    const STATUS_COMPLETED = 'completed';

    const STATUS_CANCELLED = 'cancelled';

    const STATUS_REFUNDED = 'refunded';

    const PAYMENT_STATUS_PENDING = 'pending';

    const PAYMENT_STATUS_COMPLETED = 'completed';

    const PAYMENT_STATUS_FAILED = 'failed';

    const PAYMENT_STATUS_REFUNDED = 'refunded';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'commission_value' => 'decimal:2',
            'order_date' => 'datetime',
            'commission_processed' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }
}
