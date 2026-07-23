<?php

namespace App\Modules\Income\FixedYieldInvestment;

use App\Models\SystemSetting;
use App\Services\Modules\ScheduledIncomeModule;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Collection;

/**
 * A fixed-yield investment product — structurally unlike every other
 * income module here, since it pays the investor for their *own*
 * capital rather than an upline for someone else's purchase. Members
 * hold FixedYieldInvestment records (created by an admin today — see
 * FixedYieldInvestmentResource — there's no live purchase flow
 * anywhere in this app to hook into), each earning a daily cash yield
 * from their current rank's monthly rate, capped independently at a
 * multiple of what they invested.
 */
class FixedYieldInvestmentModule implements ScheduledIncomeModule
{
    public function __construct(
        private readonly FixedYieldInvestmentService $service,
    ) {}

    public static function key(): string
    {
        return 'fixed_yield_investment';
    }

    public function label(): string
    {
        return 'Fixed Yield Investment';
    }

    public function description(): string
    {
        return 'Pays each active investment a daily cash yield from the investor\'s current rank\'s monthly rate (configured per rank), capped at a multiple of the invested amount.';
    }

    public function isEnabled(): bool
    {
        return filter_var(SystemSetting::get('fixed_yield_investment_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function setEnabled(bool $enabled): void
    {
        SystemSetting::set('fixed_yield_investment_enabled', $enabled ? 'true' : 'false', 'commission', 'boolean');
    }

    /** @return Collection<int, \App\Models\FixedYieldDailyAccrual> */
    public function run(): Collection
    {
        return $this->service->runDaily();
    }

    public function settingsSchema(): array
    {
        return [
            TextInput::make('fixed_yield_investment_cap_multiplier')
                ->label('Cap multiplier')
                ->helperText('Total lifetime cash yield per investment is capped at invested amount × this.')
                ->numeric()
                ->minValue(0)
                ->required(),
        ];
    }

    public function settingsData(): array
    {
        return [
            'fixed_yield_investment_cap_multiplier' => (float) SystemSetting::get('fixed_yield_investment_cap_multiplier', 2),
        ];
    }

    public function saveSettings(array $state): void
    {
        $value = $state['fixed_yield_investment_cap_multiplier'] ?? SystemSetting::get('fixed_yield_investment_cap_multiplier', 2);

        SystemSetting::set('fixed_yield_investment_cap_multiplier', (string) $value, 'commission', 'decimal');
    }

    public function dedicatedSettingsPage(): ?string
    {
        return null;
    }
}
