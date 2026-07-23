<?php

namespace App\Modules\Income\PersonalVolume\Filament\PersonalVolumeAccrualResource\Pages;

use App\Modules\Income\PersonalVolume\Filament\PersonalVolumeAccrualResource;
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
