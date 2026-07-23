<?php

namespace App\Modules\Income\FixedYieldInvestment\Filament\FixedYieldInvestmentResource\Pages;

use App\Modules\Income\FixedYieldInvestment\Filament\FixedYieldInvestmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFixedYieldInvestment extends EditRecord
{
    protected static string $resource = FixedYieldInvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
