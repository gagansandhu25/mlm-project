<?php

namespace App\Console\Commands;

use App\Services\Commission\PersonalVolumeCommissionService;
use Illuminate\Console\Command;

class PayPersonalVolumeCommission extends Command
{
    protected $signature = 'commission:personal-volume-daily';

    protected $description = 'Pay every active member their daily personal-volume commission percentage.';

    public function handle(PersonalVolumeCommissionService $service): int
    {
        $paid = $service->runDaily();

        $this->info("Paid personal volume commission to {$paid->count()} member(s).");

        return self::SUCCESS;
    }
}
