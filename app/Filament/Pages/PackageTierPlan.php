<?php

namespace App\Filament\Pages;

use App\Models\Commission;
use App\Models\CommissionConfiguration;
use App\Models\SystemSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

/**
 * Everything the Package Tier compensation plan needs, in one place:
 * the direct reward and which condition gates the tiers (both
 * SystemSetting-backed), and the tiers themselves as a reorderable
 * repeater — no plan_type dropdown to pick, no level number to type
 * in, no separate page. A tier's position in the list *is* its level
 * (first item = Tier 1 = the buyer's direct upline, and so on).
 *
 * On save, every CommissionConfiguration row for
 * Commission::TYPE_PACKAGE_TIER is replaced wholesale from the
 * repeater's state — this page is the single source of truth for
 * those rows; see the note on CommissionConfigurationResource.
 */
class PackageTierPlan extends Page implements HasForms
{
    use InteractsWithForms;
    use InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Package Tier Plan';

    protected static ?string $navigationGroup = 'Commission Engine';

    protected static string $view = 'filament.pages.package-tier-plan';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $tiers = CommissionConfiguration::query()
            ->where('plan_type', Commission::TYPE_PACKAGE_TIER)
            ->where('is_active', true)
            ->orderBy('level')
            ->get()
            ->map(fn (CommissionConfiguration $config) => [
                'percentage' => (float) $config->percentage,
                'qualifying_amount' => (float) ($config->settings['qualifying_amount'] ?? 0),
                'cap' => $config->cap !== null ? (float) $config->cap : null,
                'cap_period' => $config->settings['cap_period'] ?? 'monthly',
            ])
            ->values()
            ->all();

        $this->form->fill([
            'package_tier_direct_reward_enabled' => filter_var(SystemSetting::get('package_tier_direct_reward_enabled', true), FILTER_VALIDATE_BOOLEAN),
            'package_tier_direct_reward_percentage' => (float) SystemSetting::get('package_tier_direct_reward_percentage', 5),
            'package_tier_condition_type' => SystemSetting::get('package_tier_condition_type', 'own_package'),
            'tiers' => $tiers,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Direct Referral Reward')
                    ->description('Paid to the buyer\'s direct upline on every package purchase, independent of the tiers below.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('package_tier_direct_reward_enabled')
                            ->label('Enabled'),
                        TextInput::make('package_tier_direct_reward_percentage')
                            ->label('Percentage')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required(),
                    ]),

                Section::make('Tier Qualifying Condition')
                    ->description('What each tier\'s qualifying amount, below, is checked against.')
                    ->schema([
                        Select::make('package_tier_condition_type')
                            ->label('Condition type')
                            ->options([
                                'own_package' => "Earner's own package purchase",
                                'team_volume' => "Earner's team volume",
                                'buyer_package' => "Buyer's package value",
                            ])
                            ->required(),
                    ]),

                Section::make('Tiers')
                    ->description('Ordered from Tier 1 (the buyer\'s direct upline) downward — drag to reorder. A tier with no qualifying amount always pays out.')
                    ->schema([
                        Repeater::make('tiers')
                            ->label('')
                            ->addActionLabel('Add tier')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => isset($state['percentage'])
                                ? $state['percentage'].'% commission'
                                : 'New tier')
                            ->schema([
                                TextInput::make('percentage')
                                    ->label('Commission')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->required(),
                                TextInput::make('qualifying_amount')
                                    ->label('Qualifying amount')
                                    ->numeric()
                                    ->minValue(0)
                                    ->default(0)
                                    ->helperText('0 = no condition, always pays.'),
                                TextInput::make('cap')
                                    ->label('Cap (optional)')
                                    ->numeric()
                                    ->minValue(0),
                                Select::make('cap_period')
                                    ->label('Cap period')
                                    ->options(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'])
                                    ->default('monthly'),
                            ])
                            ->columns(4),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        SystemSetting::set('package_tier_direct_reward_enabled', ($state['package_tier_direct_reward_enabled'] ?? true) ? 'true' : 'false', 'commission', 'boolean');
        SystemSetting::set('package_tier_direct_reward_percentage', (string) ($state['package_tier_direct_reward_percentage'] ?? 5), 'commission', 'decimal');
        SystemSetting::set('package_tier_condition_type', $state['package_tier_condition_type'] ?? 'own_package', 'commission', 'string');

        DB::transaction(function () use ($state) {
            CommissionConfiguration::query()->where('plan_type', Commission::TYPE_PACKAGE_TIER)->delete();

            foreach (($state['tiers'] ?? []) as $index => $tier) {
                CommissionConfiguration::create([
                    'plan_type' => Commission::TYPE_PACKAGE_TIER,
                    'level' => $index + 1,
                    'percentage' => $tier['percentage'],
                    'cap' => $tier['cap'] !== '' && $tier['cap'] !== null ? $tier['cap'] : null,
                    'is_active' => true,
                    'settings' => [
                        'qualifying_amount' => $tier['qualifying_amount'] ?? 0,
                        'cap_period' => $tier['cap_period'] ?? 'monthly',
                    ],
                ]);
            }
        });

        Notification::make()
            ->title('Package Tier plan saved')
            ->success()
            ->send();
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->submit('save'),
        ];
    }
}
