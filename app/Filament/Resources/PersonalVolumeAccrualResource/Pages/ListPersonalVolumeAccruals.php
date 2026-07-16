<?php

namespace App\Filament\Resources\PersonalVolumeAccrualResource\Pages;

use App\Filament\Resources\PersonalVolumeAccrualResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPersonalVolumeAccruals extends ListRecords
{
    protected static string $resource = PersonalVolumeAccrualResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
