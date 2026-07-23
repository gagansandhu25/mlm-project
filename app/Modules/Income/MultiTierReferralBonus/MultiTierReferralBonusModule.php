<?php

namespace App\Modules\Income\MultiTierReferralBonus;

use App\Models\Commission;
use App\Models\CommissionConfiguration;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Commission\LevelLadderPayer;
use App\Services\Modules\OrderTriggeredIncomeModule;
use App\Services\TreeService;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A multi-level referral bonus, independent of whichever PlanModule is
 * active: a reorderable ladder of tiers, each gated by an admin-
 * configured qualifying amount, checked against whichever condition
 * type the admin selects (an upline's own highest package purchase,
 * their team volume, or the buyer's own package value). Originally
 * built into the (now-retired) Package Tier plan specifically; moved
 * here since the tier ladder has nothing to do with any one plan's
 * placement shape — it reuses LevelLadderPayer, the same walk/cap/
 * rank-multiplier engine every plan module's level ladder uses.
 */
class MultiTierReferralBonusModule implements OrderTriggeredIncomeModule
{
    public function __construct(
        private readonly TreeService $tree,
        private readonly LevelLadderPayer $ladder,
    ) {}

    public static function key(): string
    {
        return 'multi_tier_referral_bonus';
    }

    public function label(): string
    {
        return 'Multi-Tier Referral Bonus';
    }

    public function description(): string
    {
        return 'A reorderable ladder of tiers paying ancestors who meet a configurable qualifying condition — independent of whichever plan is active.';
    }

    public function isEnabled(): bool
    {
        return filter_var(SystemSetting::get('multi_tier_referral_bonus_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function setEnabled(bool $enabled): void
    {
        SystemSetting::set('multi_tier_referral_bonus_enabled', $enabled ? 'true' : 'false', 'commission', 'boolean');
    }

    /** @return Collection<int, Commission> */
    public function handle(Order $order): Collection
    {
        return $this->ladder->pay(
            $order,
            $order->user,
            Commission::TYPE_MULTI_TIER_REFERRAL_BONUS,
            (float) $order->commission_value,
            fn (User $upline, CommissionConfiguration $config): bool => $this->meetsQualifyingCondition($upline, $order, $config),
        );
    }

    /** Config with no qualifying amount set always pays; otherwise gated by the configured condition type. */
    private function meetsQualifyingCondition(User $upline, Order $order, CommissionConfiguration $config): bool
    {
        $requiredAmount = (float) ($config->settings['qualifying_amount'] ?? 0);

        if ($requiredAmount <= 0) {
            return true;
        }

        return $this->qualifyingAmountFor($upline, $order) >= $requiredAmount;
    }

    /**
     * Qualification is checked against package *price* (Order::amount),
     * not commission_value — tier thresholds are meant as package-price
     * tiers within the catalog's price range, distinct from
     * commission_value, which stays the commissionable base for every
     * payout percentage calculation, unchanged.
     */
    private function qualifyingAmountFor(User $upline, Order $order): float
    {
        return match (SystemSetting::get('multi_tier_referral_bonus_condition_type', 'own_package')) {
            'team_volume' => $this->tree->getTeamVolume($upline) + (float) $upline->sales_volume,
            'buyer_package' => (float) $order->amount,
            default => $this->highestPackagePurchase($upline), // 'own_package'
        };
    }

    /** The upline's own highest completed package purchase, if any. */
    private function highestPackagePurchase(User $user): float
    {
        return (float) Order::query()
            ->where('user_id', $user->id)
            ->where('status', Order::STATUS_COMPLETED)
            ->whereHas('product', fn ($query) => $query->where('is_package', true))
            ->max('amount');
    }

    /**
     * No dedicatedSettingsPage() — small enough (a condition picker plus
     * a reorderable repeater) to render inline in the Bonuses section
     * like every other income module, rather than sending the admin to
     * a separate page for it.
     */
    public function settingsSchema(): array
    {
        return [
            Select::make('multi_tier_referral_bonus_condition_type')
                ->label('Condition type')
                ->helperText('What each tier\'s qualifying amount, below, is checked against.')
                ->options([
                    'own_package' => "Earner's own package purchase",
                    'team_volume' => "Earner's team volume",
                    'buyer_package' => "Buyer's package value",
                ])
                ->required()
                ->columnSpanFull(),

            Repeater::make('multi_tier_referral_bonus_tiers')
                ->label('Tiers')
                ->helperText('Ordered from Tier 1 (the buyer\'s direct upline) downward — drag to reorder. A tier with no qualifying amount always pays out.')
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
                ->columns(4)
                ->columnSpanFull(),
        ];
    }

    public function settingsData(): array
    {
        $tiers = CommissionConfiguration::query()
            ->where('plan_type', Commission::TYPE_MULTI_TIER_REFERRAL_BONUS)
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

        return [
            'multi_tier_referral_bonus_condition_type' => SystemSetting::get('multi_tier_referral_bonus_condition_type', 'own_package'),
            'multi_tier_referral_bonus_tiers' => $tiers,
        ];
    }

    /**
     * Every CommissionConfiguration row for
     * Commission::TYPE_MULTI_TIER_REFERRAL_BONUS is replaced wholesale
     * from the repeater's state on every save — this is the single
     * source of truth for those rows; see the note on
     * CommissionConfigurationResource.
     */
    public function saveSettings(array $state): void
    {
        SystemSetting::set('multi_tier_referral_bonus_condition_type', $state['multi_tier_referral_bonus_condition_type'] ?? 'own_package', 'commission', 'string');

        DB::transaction(function () use ($state) {
            CommissionConfiguration::query()->where('plan_type', Commission::TYPE_MULTI_TIER_REFERRAL_BONUS)->delete();

            foreach (($state['multi_tier_referral_bonus_tiers'] ?? []) as $index => $tier) {
                CommissionConfiguration::create([
                    'plan_type' => Commission::TYPE_MULTI_TIER_REFERRAL_BONUS,
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
    }

    public function dedicatedSettingsPage(): ?string
    {
        return null;
    }
}
