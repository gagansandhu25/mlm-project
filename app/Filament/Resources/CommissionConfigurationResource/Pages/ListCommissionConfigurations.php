<?php

namespace App\Filament\Resources\CommissionConfigurationResource\Pages;

use App\Filament\Resources\CommissionConfigurationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCommissionConfigurations extends ListRecords
{
    protected static string $resource = CommissionConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
