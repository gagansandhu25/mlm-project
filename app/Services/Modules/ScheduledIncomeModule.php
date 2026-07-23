<?php

namespace App\Services\Modules;

use Illuminate\Support\Collection;

/**
 * An income module invoked periodically rather than per-order — e.g.
 * Personal Volume's daily accrual. `income:run-scheduled` calls run() once
 * per invocation for every enabled module of this kind; the module itself
 * decides what one "tick" means (a day, in Personal Volume's case).
 */
interface ScheduledIncomeModule extends IncomeModule
{
    /** @return Collection<int, mixed> whatever records this run created */
    public function run(): Collection;
}
