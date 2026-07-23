<?php

namespace App\Modules\Income\PersonalVolume\Filament\PersonalVolumeAccrualResource\Pages;

use App\Modules\Income\PersonalVolume\Filament\PersonalVolumeAccrualResource;
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
