<?php

namespace App\Filament\Resources\CommissionConfigurationResource\Pages;

use App\Filament\Resources\CommissionConfigurationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCommissionConfiguration extends EditRecord
{
    protected static string $resource = CommissionConfigurationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
