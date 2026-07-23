<?php

namespace App\Services;

use App\Models\Order;
use App\Services\Modules\IncomeModuleRegistry;
use App\Services\Modules\OrderTriggeredIncomeModule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Entry point for the commission workflow: receives a trigger (a
 * completed order) and stacks every enabled order-triggered income
 * module's payout together — there's no special-cased "the active
 * plan's own commission" step anymore. What used to be baked into each
 * PlanModule (Unilevel Level Commission, Binary Pairing Commission,
 * Matrix Level Commission) are themselves just income modules now,
 * indistinguishable from any other bonus. `active_plan_type` no longer
 * has any bearing on which commissions get calculated — it's purely a
 * placement/tree-shape selector (see PlanModule). Adding a new plan or
 * income type entirely is an app/Modules/ addition, not a
 * CommissionService change.
 *
 * The commission_processed guard lives here, wrapping the whole loop,
 * so a second invocation for the same order never double-pays any
 * module — previously this guard only lived inside the active plan's
 * own calculator, so a retried call would have double-paid every
 * income module stacked on top of it.
 */
class CommissionService
{
    public function __construct(
        private readonly IncomeModuleRegistry $incomeModules,
    ) {}

    public function calculateForOrder(Order $order): Collection
    {
        if ($order->status !== Order::STATUS_COMPLETED || $order->commission_processed) {
            return new Collection;
        }

        return DB::transaction(function () use ($order) {
            $order->user()->increment('sales_volume', (float) $order->commission_value);

            $commissions = new Collection;

            /** @var OrderTriggeredIncomeModule $module */
            foreach ($this->incomeModules->orderTriggered() as $module) {
                $commissions = $commissions->merge($module->handle($order));
            }

            $order->forceFill(['commission_processed' => true])->save();

            return $commissions;
        });
    }
}
