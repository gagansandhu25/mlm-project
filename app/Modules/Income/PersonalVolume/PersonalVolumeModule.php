<?php

namespace App\Modules\Income\PersonalVolume;

use App\Models\SystemSetting;
use App\Services\Modules\ScheduledIncomeModule;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Collection;

class PersonalVolumeModule implements ScheduledIncomeModule
{
    public function __construct(
        private readonly PersonalVolumeCommissionService $service,
    ) {}

    public static function key(): string
    {
        return 'personal_volume';
    }

    public function label(): string
    {
        return 'Personal Volume';
    }

    public function description(): string
    {
        return 'Pays each active member a daily percentage of their own accumulated sales volume — runs on a schedule, not triggered by any single order.';
    }

    public function isEnabled(): bool
    {
        return filter_var(SystemSetting::get('personal_volume_commission_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function setEnabled(bool $enabled): void
    {
        SystemSetting::set('personal_volume_commission_enabled', $enabled ? 'true' : 'false', 'commission', 'boolean');
    }

    /** @return Collection<int, \App\Models\PersonalVolumeAccrual> */
    public function run(): Collection
    {
        return $this->service->runDaily();
    }

    public function settingsSchema(): array
    {
        return [
            TextInput::make('personal_volume_percentage')
                ->label('Personal volume percentage (daily)')
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->suffix('%')
                ->required(),
        ];
    }

    public function settingsData(): array
    {
        return [
            'personal_volume_percentage' => (float) SystemSetting::get('personal_volume_percentage', 0),
        ];
    }

    public function saveSettings(array $state): void
    {
        $value = $state['personal_volume_percentage'] ?? SystemSetting::get('personal_volume_percentage', 0);

        SystemSetting::set('personal_volume_percentage', (string) $value, 'commission', 'decimal');
    }

    public function dedicatedSettingsPage(): ?string
    {
        return null;
    }
}
