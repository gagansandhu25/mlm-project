<?php

namespace App\Modules\Income\FixedYieldInvestment\Filament\FixedYieldInvestmentResource\Pages;

use App\Modules\Income\FixedYieldInvestment\Filament\FixedYieldInvestmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFixedYieldInvestments extends ListRecords
{
    protected static string $resource = FixedYieldInvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
