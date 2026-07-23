<?php

namespace App\Modules\Income\FixedYieldInvestment\Filament\FixedYieldDailyAccrualResource\Pages;

use App\Modules\Income\FixedYieldInvestment\Filament\FixedYieldDailyAccrualResource;
use Filament\Resources\Pages\ListRecords;

class ListFixedYieldDailyAccruals extends ListRecords
{
    protected static string $resource = FixedYieldDailyAccrualResource::class;
}
