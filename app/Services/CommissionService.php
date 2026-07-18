<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SystemSetting;
use App\Services\Commission\CommissionCalculatorRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Entry point for STEP 1-2 of the commission workflow: receives a
 * trigger (a completed order), determines the active plan type, and
 * dispatches to the matching calculator via CommissionCalculatorRegistry.
 * Which plan is "active" is a config value (SystemSetting
 * `active_plan_type`) — switching plans for a client is a config
 * change, not a code change. Adding a new plan type entirely is a
 * CommissionServiceProvider change, not a CommissionService change.
 */
class CommissionService
{
    public function __construct(
        private readonly CommissionCalculatorRegistry $calculators,
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

        return $this->calculators->for($planType)->calculate($order);
    }
}
