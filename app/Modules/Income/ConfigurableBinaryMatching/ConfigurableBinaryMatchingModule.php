<?php

namespace App\Modules\Income\ConfigurableBinaryMatching;

use App\Models\Commission;
use App\Models\CommissionConfiguration;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Commission\CommissionPayoutRecorder;
use App\Services\Commission\LevelLadderPayer;
use App\Services\Modules\MatchingBasis;
use App\Services\Modules\MatchingBasisRegistry;
use App\Services\Modules\MatchPayoutFormula;
use App\Services\Modules\MatchPayoutFormulaRegistry;
use App\Services\Modules\MatchQualificationRuleRegistry;
use App\Services\Modules\OrderTriggeredIncomeModule;
use App\Services\TreeService;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A third binary-style peer, alongside Binary Pairing Commission and
 * Hybrid Binary Matching — but instead of a fixed formula, three parts
 * of the payout are each swapped from a discoverable strategy registry
 * (same convention as ActivePackageResolver): what accrues onto a leg
 * and in what unit (MatchingBasis — dollar volume vs. head count),
 * what makes an order count at all (MatchQualificationRule — every
 * order vs. a member's first order only), and how matched pairs
 * convert into a payout (MatchPayoutFormula — flat-per-pair vs.
 * percentage-of-matched-volume). A future basis/rule/formula is a new
 * folder under app/Modules/MatchingBases|MatchQualificationRules|
 * MatchPayoutFormulas — never an edit to this module.
 *
 * Peer to Binary Pairing Commission and Hybrid Binary Matching, not a
 * replacement for either — all three independently read/write leg
 * columns (Volume basis reuses left_volume/right_volume; Count basis
 * uses its own left_count/right_count so it never collides regardless
 * of what else is enabled). Defaults to disabled; an admin picks one
 * binary matching style, not several at once, when using Volume basis.
 */
class ConfigurableBinaryMatchingModule implements OrderTriggeredIncomeModule
{
    public function __construct(
        private readonly TreeService $tree,
        private readonly CommissionPayoutRecorder $payouts,
        private readonly LevelLadderPayer $ladder,
        private readonly MatchingBasisRegistry $bases,
        private readonly MatchQualificationRuleRegistry $qualificationRules,
        private readonly MatchPayoutFormulaRegistry $payoutFormulas,
    ) {}

    public static function key(): string
    {
        return 'configurable_binary_matching';
    }

    public function label(): string
    {
        return 'Configurable Binary Matching';
    }

    public function description(): string
    {
        return 'Binary matching with a pluggable basis (volume or head count), qualification rule, and payout formula — pick a matching philosophy from the dropdowns below instead of a fixed formula. Do not enable alongside Binary Pairing Commission or Hybrid Binary Matching while using the Volume basis — all three consume the same left/right leg volume. The Count basis uses its own separate columns, so it never collides regardless of what else is enabled.';
    }

    public function isEnabled(): bool
    {
        return filter_var(SystemSetting::get('configurable_binary_matching_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function setEnabled(bool $enabled): void
    {
        SystemSetting::set('configurable_binary_matching_enabled', $enabled ? 'true' : 'false', 'commission', 'boolean');
    }

    /** @return Collection<int, Commission> */
    public function handle(Order $order): Collection
    {
        $rule = $this->qualificationRules->for(SystemSetting::get('configurable_binary_matching_qualification_rule', 'every_order'));

        if (! $rule->qualifies($order)) {
            return new Collection;
        }

        $basis = $this->bases->for(SystemSetting::get('configurable_binary_matching_basis', 'volume'));
        $formula = $this->payoutFormulas->for(SystemSetting::get('configurable_binary_matching_payout_formula', 'flat_per_pair'));

        $created = new Collection;

        foreach ($this->creditedUplines($order->user) as [$upline, $side]) {
            $created = $created->merge($this->matchForUpline($order, $upline, $side, $basis, $formula));
        }

        return $created;
    }

    /**
     * Ancestors of the buyer paired with which leg (left/right) of that
     * ancestor the buyer's branch descends through, closest ancestor
     * first — identical shape to Binary Pairing Commission's and
     * Hybrid Binary Matching's own walk.
     *
     * @return list<array{0: User, 1: string}>
     */
    private function creditedUplines(User $buyer): array
    {
        $chain = $this->tree->getAncestors($buyer)->reverse()->values();
        $child = $buyer;

        $pairs = [];
        foreach ($chain as $ancestor) {
            if ($child->position === User::POSITION_LEFT || $child->position === User::POSITION_RIGHT) {
                $pairs[] = [$ancestor, $child->position];
            }

            $child = $ancestor;
        }

        return $pairs;
    }

    /** @return Collection<int, Commission> */
    private function matchForUpline(Order $order, User $upline, string $side, MatchingBasis $basis, MatchPayoutFormula $formula): Collection
    {
        if ($upline->status !== User::STATUS_ACTIVE) {
            return new Collection;
        }

        [$leftColumn, $rightColumn] = $basis->legColumns();
        $column = $side === User::POSITION_LEFT ? $leftColumn : $rightColumn;

        $upline->forceFill([$column => (float) $upline->{$column} + $basis->creditAmount($order)])->save();

        $matchedAmount = min((float) $upline->{$leftColumn}, (float) $upline->{$rightColumn});
        $pairUnitSize = $basis->pairUnitSize();

        if ($pairUnitSize <= 0) {
            return new Collection;
        }

        $uncappedPairs = (int) floor($matchedAmount / $pairUnitSize);

        if ($uncappedPairs <= 0) {
            return new Collection;
        }

        $rankMultiplier = (float) ($upline->rank?->commission_multiplier ?? 1.0);
        $dailyRoom = $basis->remainingRoom($upline, 'daily');
        $lifetimeRoom = $basis->remainingRoom($upline, 'lifetime');

        // A 'count' basis caps how many pairs are even allowed to form,
        // before the formula runs; a 'currency' basis caps the
        // formula's dollar output, after it runs — see MatchingBasis's
        // own docblock for why these are different pipeline stages.
        if ($basis->capUnit() === 'count') {
            $pairs = (int) min($uncappedPairs, $dailyRoom, $lifetimeRoom);

            if ($pairs <= 0) {
                return new Collection;
            }

            $baseAmount = $formula->baseAmount($order, $pairs, $pairUnitSize);

            if ($baseAmount <= 0) {
                return new Collection;
            }

            $actualAmount = round($baseAmount * $rankMultiplier, 2);
        } else {
            $pairs = $uncappedPairs;
            $baseAmount = $formula->baseAmount($order, $pairs, $pairUnitSize);

            if ($baseAmount <= 0) {
                return new Collection;
            }

            $theoreticalAmount = round($baseAmount * $rankMultiplier, 2);
            $actualAmount = min($theoreticalAmount, $dailyRoom, $lifetimeRoom);
        }

        if ($actualAmount <= 0) {
            return new Collection;
        }

        // Only whole-pair amount is ever eligible to be consumed. Under
        // the 'count' cap, $pairs was already truncated to what's
        // payable, so it's consumed in full; under the 'currency' cap,
        // only the portion proportional to what was actually paid is
        // consumed — whatever the cap withheld carries forward.
        $eligibleAmount = $pairs * $pairUnitSize;
        $amountConsumed = $basis->capUnit() === 'count'
            ? $eligibleAmount
            : min($eligibleAmount, $eligibleAmount * ($actualAmount / $theoreticalAmount));

        $upline->forceFill([
            $leftColumn => (float) $upline->{$leftColumn} - $amountConsumed,
            $rightColumn => (float) $upline->{$rightColumn} - $amountConsumed,
        ])->save();

        $selfCommission = $this->payouts->record(
            order: $order,
            upline: $upline,
            level: 0,
            baseAmount: $baseAmount,
            amount: $actualAmount,
            percentage: $formula->displayPercentage(),
            rankMultiplier: $rankMultiplier,
            planType: Commission::TYPE_CONFIGURABLE_BINARY_MATCHING,
            description: 'configurable binary matching bonus',
            position: $side,
            unitsMatched: (float) $pairs,
        );

        $created = new Collection([$selfCommission]);

        if (filter_var(SystemSetting::get('configurable_binary_matching_pool_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            $poolCommissions = $this->ladder->pay(
                $order,
                $upline,
                Commission::TYPE_CONFIGURABLE_BINARY_POOL,
                $actualAmount,
                fn (User $poolUpline, CommissionConfiguration $config): bool => true,
            );

            $created = $created->merge($poolCommissions);
        }

        return $created;
    }

    public function settingsSchema(): array
    {
        $fields = [
            Select::make('configurable_binary_matching_basis')
                ->label('Matching basis')
                ->options(fn () => $this->bases->options())
                ->required()
                ->live(),

            Select::make('configurable_binary_matching_qualification_rule')
                ->label('Qualification rule')
                ->options(fn () => $this->qualificationRules->options())
                ->required()
                ->live(),

            Select::make('configurable_binary_matching_payout_formula')
                ->label('Payout formula')
                ->options(fn () => $this->payoutFormulas->options())
                ->required()
                ->live(),

            Toggle::make('configurable_binary_matching_pool_enabled')
                ->label('Pay an upline pool too')
                ->live(),
        ];

        foreach ($this->bases->all() as $basis) {
            foreach ($basis->settingsSchema() as $field) {
                $fields[] = $field
                    ->visible(fn (Get $get): bool => $get('configurable_binary_matching_basis') === $basis::key())
                    ->dehydratedWhenHidden();
            }
        }

        foreach ($this->qualificationRules->all() as $rule) {
            foreach ($rule->settingsSchema() as $field) {
                $fields[] = $field
                    ->visible(fn (Get $get): bool => $get('configurable_binary_matching_qualification_rule') === $rule::key())
                    ->dehydratedWhenHidden();
            }
        }

        foreach ($this->payoutFormulas->all() as $formula) {
            foreach ($formula->settingsSchema() as $field) {
                $fields[] = $field
                    ->visible(fn (Get $get): bool => $get('configurable_binary_matching_payout_formula') === $formula::key())
                    ->dehydratedWhenHidden();
            }
        }

        $fields[] = Repeater::make('configurable_binary_matching_pool_levels')
            ->label('Pool levels')
            ->helperText('Percentage of the pool paid to each level of the matched person\'s own upline, closest first. Must sum to 100%.')
            ->addActionLabel('Add level')
            ->reorderable()
            ->simple(
                TextInput::make('percentage')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%')
                    ->required(),
            )
            ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                $sum = collect($value)->sum(fn ($row) => (float) ($row['percentage'] ?? 0));

                if (abs($sum - 100.0) > 0.01) {
                    $fail("Pool level percentages must sum to 100% (currently {$sum}%).");
                }
            })
            ->visible(fn (Get $get): bool => (bool) $get('configurable_binary_matching_pool_enabled'))
            ->dehydratedWhenHidden()
            ->columnSpanFull();

        return $fields;
    }

    public function settingsData(): array
    {
        $levels = CommissionConfiguration::query()
            ->where('plan_type', Commission::TYPE_CONFIGURABLE_BINARY_POOL)
            ->where('is_active', true)
            ->orderBy('level')
            ->get()
            ->map(fn (CommissionConfiguration $config) => (float) $config->percentage)
            ->values()
            ->all();

        $data = [
            'configurable_binary_matching_basis' => SystemSetting::get('configurable_binary_matching_basis', 'volume'),
            'configurable_binary_matching_qualification_rule' => SystemSetting::get('configurable_binary_matching_qualification_rule', 'every_order'),
            'configurable_binary_matching_payout_formula' => SystemSetting::get('configurable_binary_matching_payout_formula', 'flat_per_pair'),
            'configurable_binary_matching_pool_enabled' => filter_var(SystemSetting::get('configurable_binary_matching_pool_enabled', true), FILTER_VALIDATE_BOOLEAN),
            'configurable_binary_matching_pool_levels' => $levels ?: [33, 27, 20, 13, 7],
        ];

        foreach ($this->bases->all() as $basis) {
            $data = [...$data, ...$basis->settingsData()];
        }

        foreach ($this->qualificationRules->all() as $rule) {
            $data = [...$data, ...$rule->settingsData()];
        }

        foreach ($this->payoutFormulas->all() as $formula) {
            $data = [...$data, ...$formula->settingsData()];
        }

        return $data;
    }

    /**
     * Every discovered basis/rule/formula's own saveSettings() runs
     * unconditionally (each reads only its own namespaced keys) so
     * switching away from a strategy and back never silently loses its
     * config — same reasoning Settings.php applies to income modules
     * generally, not just the currently-enabled one.
     */
    public function saveSettings(array $state): void
    {
        SystemSetting::set('configurable_binary_matching_basis', $state['configurable_binary_matching_basis'] ?? SystemSetting::get('configurable_binary_matching_basis', 'volume'), 'commission', 'string');
        SystemSetting::set('configurable_binary_matching_qualification_rule', $state['configurable_binary_matching_qualification_rule'] ?? SystemSetting::get('configurable_binary_matching_qualification_rule', 'every_order'), 'commission', 'string');
        SystemSetting::set('configurable_binary_matching_payout_formula', $state['configurable_binary_matching_payout_formula'] ?? SystemSetting::get('configurable_binary_matching_payout_formula', 'flat_per_pair'), 'commission', 'string');
        SystemSetting::set('configurable_binary_matching_pool_enabled', ($state['configurable_binary_matching_pool_enabled'] ?? true) ? 'true' : 'false', 'commission', 'boolean');

        foreach ($this->bases->all() as $basis) {
            $basis->saveSettings($state);
        }

        foreach ($this->qualificationRules->all() as $rule) {
            $rule->saveSettings($state);
        }

        foreach ($this->payoutFormulas->all() as $formula) {
            $formula->saveSettings($state);
        }

        DB::transaction(function () use ($state) {
            CommissionConfiguration::query()->where('plan_type', Commission::TYPE_CONFIGURABLE_BINARY_POOL)->delete();

            foreach (($state['configurable_binary_matching_pool_levels'] ?? []) as $index => $percentage) {
                CommissionConfiguration::create([
                    'plan_type' => Commission::TYPE_CONFIGURABLE_BINARY_POOL,
                    'level' => $index + 1,
                    'percentage' => $percentage,
                    'is_active' => true,
                    'settings' => [],
                ]);
            }
        });
    }

    public function dedicatedSettingsPage(): ?string
    {
        return null;
    }
}
