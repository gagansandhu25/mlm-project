<?php

namespace App\Filament\Resources\PersonalVolumeAccrualResource\Pages;

use App\Filament\Resources\PersonalVolumeAccrualResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPersonalVolumeAccrual extends EditRecord
{
    protected static string $resource = PersonalVolumeAccrualResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
