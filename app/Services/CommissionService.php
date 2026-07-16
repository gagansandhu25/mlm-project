<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SystemSetting;
use App\Services\Commission\BinaryCommissionCalculator;
use App\Services\Commission\MatrixCommissionCalculator;
use App\Services\Commission\UnilevelCommissionCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Entry point for STEP 1-2 of the commission workflow: receives a
 * trigger (a completed order), determines the active plan type, and
 * dispatches to the matching calculator. Which plan is "active" is a
 * config value (SystemSetting `active_plan_type`) — switching plans
 * for a client is a config change, not a code change.
 */
class CommissionService
{
    public function __construct(
        private readonly UnilevelCommissionCalculator $unilevel,
        private readonly BinaryCommissionCalculator $binary,
        private readonly MatrixCommissionCalculator $matrix,
    ) {}

    public function calculateForOrder(Order $order): Collection
    {
        if ($order->status !== Order::STATUS_COMPLETED) {
            return new Collection;
        }

        if (! $order->commission_processed) {
            DB::transaction(function () use ($order) {
                $order->user()->increment('sales_volume', (float) $order->commission_value);
            });
        }

        $planType = SystemSetting::get('active_plan_type', 'unilevel');

        return match ($planType) {
            'unilevel' => $this->unilevel->calculate($order),
            'binary' => $this->binary->calculate($order),
            'matrix' => $this->matrix->calculate($order),
            default => throw new \InvalidArgumentException("Unknown plan type [{$planType}]."),
        };
    }
}
