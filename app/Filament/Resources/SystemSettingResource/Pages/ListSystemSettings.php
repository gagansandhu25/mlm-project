<?php

namespace App\Filament\Resources\SystemSettingResource\Pages;

use App\Filament\Resources\SystemSettingResource;
use App\Models\SystemSetting;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSystemSettings extends ListRecords
{
    protected static string $resource = SystemSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('All')];

        foreach (SystemSetting::query()->distinct()->orderBy('group')->pluck('group') as $group) {
            $tabs[$group] = Tab::make(ucfirst($group))
                ->query(fn (Builder $query) => $query->where('group', $group));
        }

        return $tabs;
    }
}
