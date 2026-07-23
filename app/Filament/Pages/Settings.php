<?php

namespace App\Filament\Pages;

use App\Models\SystemSetting;
use App\Services\Modules\IncomeModule;
use App\Services\Modules\IncomeModuleRegistry;
use App\Services\Modules\PlanModule;
use App\Services\Modules\PlanModuleRegistry;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Placeholder;
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
 *
 * Plan-specific and income-module-specific fields are never hardcoded
 * here — they're pulled from PlanModuleRegistry/IncomeModuleRegistry, so
 * adding a new plan or bonus type never means editing this page.
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
        $data = [
            'company_name' => SystemSetting::get('company_name'),
            'support_email' => SystemSetting::get('support_email'),
            'active_plan_type' => SystemSetting::get('active_plan_type', 'unilevel'),
            'minimum_payout_threshold' => (float) SystemSetting::get('minimum_payout_threshold', 50),
            'withdrawal_fee_percentage' => (float) SystemSetting::get('withdrawal_fee_percentage', 0),
        ];

        foreach ($this->planModules()->all() as $module) {
            $data = [...$data, ...$module->settingsData()];
        }

        foreach ($this->incomeModules()->all() as $module) {
            $data["income_enabled_{$module->key()}"] = $module->isEnabled();
            $data = [...$data, ...$module->settingsData()];
        }

        $this->form->fill($data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('General')
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')->required(),
                        TextInput::make('support_email')->email()->required(),
                    ]),

                Section::make('Placement')
                    ->description('Where new recruits land in the tree. Purely structural — which commissions actually get paid is configured entirely under Income below.')
                    ->columns(2)
                    ->schema([
                        Select::make('active_plan_type')
                            ->label('Active plan')
                            ->options(fn () => $this->planModules()->options())
                            ->required()
                            ->live()
                            // disabled() implies dehydrated(false) by default,
                            // which would drop this key from getState()
                            // entirely and make save() write null over it.
                            ->dehydrated()
                            ->helperText(fn (Get $get): ?string => $this->planModules()->all()
                                ->first(fn (PlanModule $m) => $m->key() === $get('active_plan_type'))
                                ?->description()),

                        ...$this->planSpecificFields(),
                    ]),

                Section::make('Income')
                    ->description('Every way this business pays a member — base plan commissions and stacked bonuses alike. Any combination can be enabled at once.')
                    ->schema($this->incomeModuleSections()),

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

    /** @return array<\Filament\Forms\Components\Component> */
    private function planSpecificFields(): array
    {
        $fields = [];

        foreach ($this->planModules()->all() as $module) {
            foreach ($module->settingsSchema() as $field) {
                $fields[] = $field
                    ->visible(fn (Get $get): bool => $get('active_plan_type') === $module->key())
                    ->dehydratedWhenHidden();
            }

            if ($module->dedicatedSettingsPage() !== null) {
                $pageClass = $module->dedicatedSettingsPage();

                $fields[] = Placeholder::make("dedicated_page_{$module->key()}")
                    ->label('')
                    ->content("Configure {$module->label()} on its dedicated ".'"'.$pageClass::getNavigationLabel().'" page.')
                    ->visible(fn (Get $get): bool => $get('active_plan_type') === $module->key())
                    ->columnSpanFull();
            }
        }

        return $fields;
    }

    /**
     * One bordered, collapsible Section per income module — rather than
     * flattening every bonus's fields into a single shared section —
     * so it stays clear at a glance which fields belong to which bonus
     * as the number of enabled bonuses grows. Starts collapsed for a
     * currently-disabled bonus to keep the page short; the admin can
     * still expand it to turn the bonus on.
     *
     * @return array<Section>
     */
    private function incomeModuleSections(): array
    {
        return $this->incomeModules()->all()
            ->map(function (IncomeModule $module) {
                $fields = [
                    Toggle::make("income_enabled_{$module->key()}")
                        ->label('Enabled')
                        ->live()
                        ->columnSpanFull(),
                ];

                foreach ($module->settingsSchema() as $field) {
                    $fields[] = $this->gateFieldByModuleEnabled($field, $module);
                }

                if ($module->dedicatedSettingsPage() !== null) {
                    $pageClass = $module->dedicatedSettingsPage();

                    $fields[] = Placeholder::make("dedicated_page_{$module->key()}")
                        ->label('')
                        ->content("Configure {$module->label()} on its dedicated ".'"'.$pageClass::getNavigationLabel().'" page.')
                        ->visible(fn (Get $get): bool => (bool) $get("income_enabled_{$module->key()}"))
                        ->columnSpanFull();
                }

                return Section::make($module->label())
                    ->description($module->description())
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(! $module->isEnabled())
                    ->schema($fields);
            })
            ->all();
    }

    /**
     * Wraps $field's own visible() condition — if it already has one,
     * e.g. Configurable Binary Matching's own cascading basis/rule/
     * formula dropdowns — with this module's enabled toggle, instead of
     * overwriting it outright. Filament's visible() replaces the
     * condition wholesale (last call wins — see
     * Filament\Forms\Components\Concerns\CanBeHidden::visible()), so
     * the previous plain `$field->visible(fn (Get $get) => ...)` here
     * silently clobbered any per-field visibility a module's own
     * settingsSchema() had already set, making every one of its fields
     * appear at once regardless of which strategy was actually
     * selected. Reading the field's own condition via Closure::bind is
     * necessary because CanBeHidden's $isVisible property is protected
     * and has no public getter; evaluating it lazily inside the new
     * closure (rather than eagerly now) preserves normal Filament
     * reactivity for modules with no condition of their own too, since
     * $field->evaluate(true) is just `true`.
     */
    private function gateFieldByModuleEnabled(Component $field, IncomeModule $module): Component
    {
        $ownCondition = \Closure::bind(fn () => $this->isVisible, $field, get_class($field))();

        return $field
            ->visible(function (Get $get) use ($field, $ownCondition, $module): bool {
                if (! (bool) $get("income_enabled_{$module->key()}")) {
                    return false;
                }

                return $field->evaluate($ownCondition);
            })
            ->dehydratedWhenHidden();
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
        SystemSetting::set('minimum_payout_threshold', (string) $value('minimum_payout_threshold'), 'payout', 'decimal');
        SystemSetting::set('withdrawal_fee_percentage', (string) $value('withdrawal_fee_percentage'), 'payout', 'decimal');

        $this->planModules()->for($value('active_plan_type'))->saveSettings($state);

        foreach ($this->incomeModules()->all() as $module) {
            $module->setEnabled((bool) $value("income_enabled_{$module->key()}"));
            $module->saveSettings($state);
        }

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    private function planModules(): PlanModuleRegistry
    {
        return app(PlanModuleRegistry::class);
    }

    private function incomeModules(): IncomeModuleRegistry
    {
        return app(IncomeModuleRegistry::class);
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
