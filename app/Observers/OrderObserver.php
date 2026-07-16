<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\CommissionService;

/**
 * STEP 1 of the commission workflow: a completed order is the trigger
 * that kicks off commission calculation. Guarded by
 * `commission_processed` so edits to an already-paid order don't
 * double-pay commissions.
 */
class OrderObserver
{
    public function saved(Order $order): void
    {
        if ($order->status === Order::STATUS_COMPLETED && ! $order->commission_processed) {
            app(CommissionService::class)->calculateForOrder($order);
        }
    }
}
