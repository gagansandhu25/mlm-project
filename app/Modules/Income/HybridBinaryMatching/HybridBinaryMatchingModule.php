<?php

namespace App\Modules\Income\HybridBinaryMatching;

use App\Models\Commission;
use App\Models\CommissionConfiguration;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Commission\CommissionPayoutRecorder;
use App\Services\Commission\LevelLadderPayer;
use App\Services\Modules\ActivePackageResolverRegistry;
use App\Services\Modules\OrderTriggeredIncomeModule;
use App\Services\TreeService;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A discrete-pair variant of binary matching: the weaker leg's volume
 * is chunked into whole pair-value units (e.g. $25), each pair pays
 * the matched person a flat percentage, and an equal amount — whatever
 * self actually received, after caps — funds a pool distributed across
 * that same person's own upline (a dynamic, admin-defined list of
 * levels, not fixed at any particular count). Two independent caps sit
 * on the self payout: a daily one (resets every day) and a lifetime
 * one (never resets), both computed as an admin-configurable multiple
 * of the person's "active package" value, itself resolved by whichever
 * ActivePackageResolver is currently selected.
 *
 * Peer to Binary Pairing Commission, not a replacement — both
 * independently read and write the same left/right leg volume, so
 * enabling both at once would have them silently compete over it.
 * Defaults to disabled for that reason; an admin picks one matching
 * style, not both.
 */
class HybridBinaryMatchingModule implements OrderTriggeredIncomeModule
{
    public function __construct(
        private readonly TreeService $tree,
        private readonly CommissionPayoutRecorder $payouts,
        private readonly LevelLadderPayer $ladder,
        private readonly ActivePackageResolverRegistry $resolvers,
    ) {}

    public static function key(): string
    {
        return 'hybrid_binary_matching';
    }

    public function label(): string
    {
        return 'Hybrid Binary Matching';
    }

    public function description(): string
    {
        return 'Discrete-pair matching with a self payout plus an upline pool, capped daily and for life against the earner\'s active package. Do not enable alongside Binary Pairing Commission — both independently consume the same left/right leg volume.';
    }

    public function isEnabled(): bool
    {
        return filter_var(SystemSetting::get('hybrid_binary_matching_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function setEnabled(bool $enabled): void
    {
        SystemSetting::set('hybrid_binary_matching_enabled', $enabled ? 'true' : 'false', 'commission', 'boolean');
    }

    /** @return Collection<int, Commission> */
    public function handle(Order $order): Collection
    {
        $created = new Collection;

        foreach ($this->creditedUplines($order->user) as [$upline, $side]) {
            $created = $created->merge($this->matchForUpline($order, $upline, $side));
        }

        return $created;
    }

    /**
     * Ancestors of the buyer paired with which leg (left/right) of that
     * ancestor the buyer's branch descends through, closest ancestor
     * first — identical shape to Binary Pairing Commission's own walk,
     * since both need to know which leg each ancestor's volume grew on.
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
    private function matchForUpline(Order $order, User $upline, string $side): Collection
    {
        if ($upline->status !== User::STATUS_ACTIVE) {
            return new Collection;
        }

        $column = $side === User::POSITION_LEFT ? 'left_volume' : 'right_volume';
        $upline->forceFill([$column => (float) $upline->{$column} + (float) $order->commission_value])->save();

        $matchedVolume = min((float) $upline->left_volume, (float) $upline->right_volume);

        $pairValue = (float) SystemSetting::get('hybrid_binary_matching_pair_value', 25);

        if ($pairValue <= 0) {
            return new Collection;
        }

        $pairs = (int) floor($matchedVolume / $pairValue);

        if ($pairs <= 0) {
            return new Collection;
        }

        $selfPercentage = (float) SystemSetting::get('hybrid_binary_matching_self_percentage', 7);
        $baseAmount = round($pairs * $pairValue * ($selfPercentage / 100), 2);

        if ($baseAmount <= 0) {
            return new Collection;
        }

        $rankMultiplier = (float) ($upline->rank?->commission_multiplier ?? 1.0);
        $theoreticalAmount = round($baseAmount * $rankMultiplier, 2);

        $actualAmount = min(
            $theoreticalAmount,
            $this->remainingDailyRoom($upline),
            $this->remainingLifetimeRoom($upline),
        );

        if ($actualAmount <= 0) {
            return new Collection;
        }

        // Only whole-pair volume is ever eligible to be consumed — any
        // remainder that didn't form a complete pair stays on the legs
        // regardless of capping. Of that eligible amount, only the
        // portion proportional to what was actually paid is consumed;
        // whatever a cap withheld on top carries forward unmatched too.
        $eligibleVolume = $pairs * $pairValue;
        $volumeConsumed = min($eligibleVolume, $eligibleVolume * ($actualAmount / $theoreticalAmount));

        $upline->forceFill([
            'left_volume' => (float) $upline->left_volume - $volumeConsumed,
            'right_volume' => (float) $upline->right_volume - $volumeConsumed,
        ])->save();

        $selfCommission = $this->payouts->record(
            order: $order,
            upline: $upline,
            level: 0,
            baseAmount: $baseAmount,
            amount: $actualAmount,
            percentage: $selfPercentage,
            rankMultiplier: $rankMultiplier,
            planType: Commission::TYPE_HYBRID_BINARY_MATCHING,
            description: 'hybrid binary matching bonus',
            position: $side,
        );

        // The pool is funded at the same amount self actually received —
        // if a cap truncated self's payout, the pool shrinks with it.
        $poolCommissions = $this->ladder->pay(
            $order,
            $upline,
            Commission::TYPE_HYBRID_BINARY_POOL,
            $actualAmount,
            fn (User $poolUpline, CommissionConfiguration $config): bool => true,
        );

        return (new Collection([$selfCommission]))->merge($poolCommissions);
    }

    private function remainingDailyRoom(User $user): float
    {
        $cap = $this->resolveActivePackage($user) * (float) SystemSetting::get('hybrid_binary_matching_daily_cap_multiplier', 2);

        return max(0.0, $cap - $this->selfEarningsSince($user, now()->startOfDay()));
    }

    private function remainingLifetimeRoom(User $user): float
    {
        $cap = $this->resolveActivePackage($user) * (float) SystemSetting::get('hybrid_binary_matching_lifetime_cap_multiplier', 5);

        return max(0.0, $cap - $this->selfEarningsSince($user, null));
    }

    /** $since null means no lower bound at all — the lifetime total. */
    private function selfEarningsSince(User $user, ?Carbon $since): float
    {
        $query = Commission::query()
            ->where('user_id', $user->id)
            ->where('plan_type', Commission::TYPE_HYBRID_BINARY_MATCHING)
            ->whereIn('status', [Commission::STATUS_PENDING, Commission::STATUS_PAID]);

        if ($since !== null) {
            $query->where('calculated_at', '>=', $since);
        }

        return (float) $query->sum('amount');
    }

    private function resolveActivePackage(User $user): float
    {
        $resolverKey = SystemSetting::get('hybrid_binary_matching_active_package_resolver', 'highest_package_purchase');

        return $this->resolvers->for($resolverKey)->resolve($user);
    }

    public function settingsSchema(): array
    {
        return [
            TextInput::make('hybrid_binary_matching_pair_value')
                ->label('Pair value')
                ->numeric()
                ->minValue(0.01)
                ->prefix('$')
                ->required(),

            TextInput::make('hybrid_binary_matching_self_percentage')
                ->label('Self percentage')
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->suffix('%')
                ->required(),

            Select::make('hybrid_binary_matching_active_package_resolver')
                ->label('Active package rule')
                ->options(fn () => $this->resolvers->options())
                ->required(),

            TextInput::make('hybrid_binary_matching_daily_cap_multiplier')
                ->label('Daily cap multiplier')
                ->helperText('Daily cap = active package × this.')
                ->numeric()
                ->minValue(0)
                ->required(),

            TextInput::make('hybrid_binary_matching_lifetime_cap_multiplier')
                ->label('Lifetime cap multiplier')
                ->helperText('Lifetime cap = active package × this. Never resets.')
                ->numeric()
                ->minValue(0)
                ->required(),

            Repeater::make('hybrid_binary_matching_pool_levels')
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
                    // Even in simple() mode, each row is still wrapped
                    // under the sub-field's own name at this point —
                    // Repeater only flattens to raw scalars later, when
                    // save() dehydrates the form's final getState().
                    $sum = collect($value)->sum(fn ($row) => (float) ($row['percentage'] ?? 0));

                    if (abs($sum - 100.0) > 0.01) {
                        $fail("Pool level percentages must sum to 100% (currently {$sum}%).");
                    }
                })
                ->columnSpanFull(),
        ];
    }

    public function settingsData(): array
    {
        $levels = CommissionConfiguration::query()
            ->where('plan_type', Commission::TYPE_HYBRID_BINARY_POOL)
            ->where('is_active', true)
            ->orderBy('level')
            ->get()
            ->map(fn (CommissionConfiguration $config) => (float) $config->percentage)
            ->values()
            ->all();

        return [
            'hybrid_binary_matching_pair_value' => (float) SystemSetting::get('hybrid_binary_matching_pair_value', 25),
            'hybrid_binary_matching_self_percentage' => (float) SystemSetting::get('hybrid_binary_matching_self_percentage', 7),
            'hybrid_binary_matching_active_package_resolver' => SystemSetting::get('hybrid_binary_matching_active_package_resolver', 'highest_package_purchase'),
            'hybrid_binary_matching_daily_cap_multiplier' => (float) SystemSetting::get('hybrid_binary_matching_daily_cap_multiplier', 2),
            'hybrid_binary_matching_lifetime_cap_multiplier' => (float) SystemSetting::get('hybrid_binary_matching_lifetime_cap_multiplier', 5),
            'hybrid_binary_matching_pool_levels' => $levels ?: [33, 27, 20, 13, 7],
        ];
    }

    /**
     * Every CommissionConfiguration row for
     * Commission::TYPE_HYBRID_BINARY_POOL is replaced wholesale from
     * the repeater's state on every save — this is the single source
     * of truth for those rows, same pattern as Multi-Tier Referral
     * Bonus's own tiers.
     */
    public function saveSettings(array $state): void
    {
        SystemSetting::set('hybrid_binary_matching_pair_value', (string) ($state['hybrid_binary_matching_pair_value'] ?? SystemSetting::get('hybrid_binary_matching_pair_value', 25)), 'commission', 'decimal');
        SystemSetting::set('hybrid_binary_matching_self_percentage', (string) ($state['hybrid_binary_matching_self_percentage'] ?? SystemSetting::get('hybrid_binary_matching_self_percentage', 7)), 'commission', 'decimal');
        SystemSetting::set('hybrid_binary_matching_active_package_resolver', $state['hybrid_binary_matching_active_package_resolver'] ?? SystemSetting::get('hybrid_binary_matching_active_package_resolver', 'highest_package_purchase'), 'commission', 'string');
        SystemSetting::set('hybrid_binary_matching_daily_cap_multiplier', (string) ($state['hybrid_binary_matching_daily_cap_multiplier'] ?? SystemSetting::get('hybrid_binary_matching_daily_cap_multiplier', 2)), 'commission', 'decimal');
        SystemSetting::set('hybrid_binary_matching_lifetime_cap_multiplier', (string) ($state['hybrid_binary_matching_lifetime_cap_multiplier'] ?? SystemSetting::get('hybrid_binary_matching_lifetime_cap_multiplier', 5)), 'commission', 'decimal');

        DB::transaction(function () use ($state) {
            CommissionConfiguration::query()->where('plan_type', Commission::TYPE_HYBRID_BINARY_POOL)->delete();

            foreach (($state['hybrid_binary_matching_pool_levels'] ?? []) as $index => $percentage) {
                CommissionConfiguration::create([
                    'plan_type' => Commission::TYPE_HYBRID_BINARY_POOL,
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
