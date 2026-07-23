<?php

namespace App\Console\Commands;

use App\Services\Modules\IncomeModuleRegistry;
use Illuminate\Console\Command;

/**
 * Runs every enabled ScheduledIncomeModule once (Personal Volume's daily
 * accrual today). Adding a future scheduled bonus (leadership pool, car
 * fund, rank maintenance bonus) never means touching this command — it
 * only needs a new app/Modules/{Name}/ implementing ScheduledIncomeModule.
 */
class RunScheduledIncomeModules extends Command
{
    protected $signature = 'income:run-scheduled';

    protected $description = 'Run every enabled scheduled income module once (e.g. daily personal volume commission).';

    public function handle(IncomeModuleRegistry $modules): int
    {
        foreach ($modules->scheduled() as $module) {
            $paid = $module->run();

            $this->info("{$module->label()}: {$paid->count()} record(s).");
        }

        return self::SUCCESS;
    }
}
