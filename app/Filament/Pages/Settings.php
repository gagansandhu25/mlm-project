<?php

namespace App\Filament\Pages;

use App\Models\SystemSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;

/**
 * Every business-editable SystemSetting in one form, grouped to match
 * their `group` column (general/commission/payout) instead of the
 * generic SystemSettingResource's one-row-at-a-time raw key/value CRUD.
 * `installed_at` is deliberately excluded — it's a system marker set
 * once by the install wizard, not a setting an admin edits.
 */
class Settings extends Page implements HasForms
{
    use InteractsWithForms;
    use InteractsWithFormActions;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?int $navigationSort = -1;

    protected static string $view = 'filament.pages.settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'company_name' => SystemSetting::get('company_name'),
            'support_email' => SystemSetting::get('support_email'),
            'active_plan_type' => SystemSetting::get('active_plan_type', 'unilevel'),
            'matrix_width' => (int) SystemSetting::get('matrix_width', 3),
            'binary_pair_percentage' => (float) SystemSetting::get('binary_pair_percentage', 10),
            'personal_volume_commission_enabled' => filter_var(SystemSetting::get('personal_volume_commission_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'personal_volume_percentage' => (float) SystemSetting::get('personal_volume_percentage', 0),
            'minimum_payout_threshold' => (float) SystemSetting::get('minimum_payout_threshold', 50),
            'withdrawal_fee_percentage' => (float) SystemSetting::get('withdrawal_fee_percentage', 0),
        ]);
    }

    public function form(Form $form): Form
    {
        $installed = SystemSetting::get('installed_at') !== null;

        return $form
            ->schema([
                Section::make('General')
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')->required(),
                        TextInput::make('support_email')->email()->required(),
                    ]),

                Section::make('Commission Plan')
                    ->columns(2)
                    ->schema([
                        Select::make('active_plan_type')
                            ->label('Active plan')
                            ->options([
                                'unilevel' => 'Unilevel',
                                'binary' => 'Binary',
                                'matrix' => 'Matrix',
                            ])
                            ->required()
                            ->live()
                            ->disabled($installed)
                            // disabled() implies dehydrated(false) by default,
                            // which would drop this key from getState()
                            // entirely and make save() write null over it.
                            ->dehydrated()
                            ->helperText($installed
                                ? 'Locked after install — changing the network plan for a live business retroactively changes how existing commissions are interpreted.'
                                : null),
                        TextInput::make('matrix_width')
                            ->label('Matrix width')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->visible(fn (Get $get) => $get('active_plan_type') === 'matrix')
                            // dehydrated() alone isn't enough for a field
                            // hidden via visible() — Filament gates that
                            // case separately. Without this, switching away
                            // from matrix would null out its stored width.
                            ->dehydratedWhenHidden(),
                        TextInput::make('binary_pair_percentage')
                            ->label('Binary pairing percentage')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required()
                            ->visible(fn (Get $get) => $get('active_plan_type') === 'binary')
                            ->dehydratedWhenHidden(),
                        Toggle::make('personal_volume_commission_enabled')
                            ->label('Daily personal volume commission enabled')
                            ->live(),
                        TextInput::make('personal_volume_percentage')
                            ->label('Personal volume percentage (daily)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required()
                            ->visible(fn (Get $get) => $get('personal_volume_commission_enabled'))
                            ->dehydratedWhenHidden(),
                    ]),

                Section::make('Payouts')
                    ->columns(2)
                    ->schema([
                        TextInput::make('minimum_payout_threshold')
                            ->label('Minimum payout threshold')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('$')
                            ->required(),
                        TextInput::make('withdrawal_fee_percentage')
                            ->label('Withdrawal fee')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        // Defense in depth on top of the dehydrated()/dehydratedWhenHidden()
        // flags above: a field missing from $state (e.g. a future field
        // added here without the right dehydration flag) falls back to its
        // current stored value instead of silently overwriting it with
        // null — this table has no soft-delete/undo, so a bad write here
        // is real data loss for the whole business's configuration.
        $value = fn (string $key) => array_key_exists($key, $state) ? $state[$key] : SystemSetting::get($key);

        SystemSetting::set('company_name', $value('company_name'), 'general', 'string');
        SystemSetting::set('support_email', $value('support_email'), 'general', 'string');
        SystemSetting::set('active_plan_type', $value('active_plan_type'), 'commission', 'string');
        SystemSetting::set('matrix_width', (string) $value('matrix_width'), 'commission', 'integer');
        SystemSetting::set('binary_pair_percentage', (string) $value('binary_pair_percentage'), 'commission', 'integer');
        SystemSetting::set('personal_volume_commission_enabled', $value('personal_volume_commission_enabled') ? 'true' : 'false', 'commission', 'boolean');
        SystemSetting::set('personal_volume_percentage', (string) $value('personal_volume_percentage'), 'commission', 'decimal');
        SystemSetting::set('minimum_payout_threshold', (string) $value('minimum_payout_threshold'), 'payout', 'decimal');
        SystemSetting::set('withdrawal_fee_percentage', (string) $value('withdrawal_fee_percentage'), 'payout', 'decimal');

        Notification::make()
            ->title('Settings saved')
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
                ->label('Save settings')
                ->submit('save'),
        ];
    }
}
