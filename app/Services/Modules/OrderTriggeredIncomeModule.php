<?php

namespace App\Services\Modules;

use App\Models\Order;
use Illuminate\Support\Collection;

/**
 * An income module that stacks on top of the active plan's payout on every
 * completed order — e.g. a future fast-start or matching bonus. No module
 * implements this yet; CommissionService's loop over these is a no-op
 * until one does, which is exactly the point: adding one never requires
 * touching CommissionService again.
 */
interface OrderTriggeredIncomeModule extends IncomeModule
{
    /** @return Collection<int, \App\Models\Commission> */
    public function handle(Order $order): Collection;
}
