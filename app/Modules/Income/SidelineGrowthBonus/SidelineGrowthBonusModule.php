<?php

namespace App\Modules\Income\SidelineGrowthBonus;

use App\Models\Commission;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Commission\CommissionPayoutRecorder;
use App\Services\Modules\OrderTriggeredIncomeModule;
use App\Services\TreeService;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Collection;

/**
 * Pays a flat percentage of a new package to the first upline who is
 * NOT one of their own parent's first two ("A/B leg") children —
 * rewards deep sideline growth rather than always stacking under the
 * top two legs. Independent of whichever PlanModule is active, but
 * structurally can only ever pay out under Unilevel (no per-parent
 * child limit) or Matrix with a width of 3 or more: Binary, and a
 * 2-wide Matrix, never produce a 3rd child, so there's never anyone
 * to reward there. Only ever pays one upline per event — the walk
 * stops at the first qualifying ancestor, whether or not they can
 * actually be paid.
 */
class SidelineGrowthBonusModule implements OrderTriggeredIncomeModule
{
    public function __construct(
        private readonly TreeService $tree,
        private readonly CommissionPayoutRecorder $payouts,
    ) {}

    public static function key(): string
    {
        return 'sideline_growth_bonus';
    }

    public function label(): string
    {
        return 'Sideline Growth Bonus';
    }

    public function description(): string
    {
        return 'Pays the first upline who isn\'t one of their own parent\'s first two ("A/B leg") children — rewards deep sideline growth. Only pays out under Unilevel, or Matrix with a width of 3 or more.';
    }

    public function isEnabled(): bool
    {
        return filter_var(SystemSetting::get('sideline_growth_bonus_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function setEnabled(bool $enabled): void
    {
        SystemSetting::set('sideline_growth_bonus_enabled', $enabled ? 'true' : 'false', 'commission', 'boolean');
    }

    /** @return Collection<int, Commission> */
    public function handle(Order $order): Collection
    {
        if (! (bool) ($order->product?->is_package)) {
            return new Collection;
        }

        $commission = $this->paySidelineUpline($order);

        return $commission ? new Collection([$commission]) : new Collection;
    }

    /**
     * Walks the buyer's ancestors closest-first, skipping anyone who's
     * their own parent's 1st or 2nd child (or has no parent at all —
     * root never qualifies). The first ancestor beyond that either gets
     * paid, or — if inactive — nobody does; the walk never cascades
     * past the first qualifying position to find another candidate.
     */
    private function paySidelineUpline(Order $order): ?Commission
    {
        $chain = $this->tree->getAncestors($order->user)->reverse()->values(); // closest-first

        foreach ($chain as $ancestor) {
            $rank = $this->tree->siblingRank($ancestor);

            if ($rank === null || $rank <= 2) {
                continue;
            }

            if ($ancestor->status !== User::STATUS_ACTIVE) {
                return null;
            }

            return $this->payAncestor($order, $ancestor);
        }

        return null;
    }

    private function payAncestor(Order $order, User $upline): ?Commission
    {
        $percentage = (float) SystemSetting::get('sideline_growth_bonus_percentage', 10);
        $baseAmount = round((float) $order->commission_value * ($percentage / 100), 2);

        if ($baseAmount <= 0) {
            return null;
        }

        $rankMultiplier = (float) ($upline->rank?->commission_multiplier ?? 1.0);
        $amount = round($baseAmount * $rankMultiplier, 2);

        if ($amount <= 0) {
            return null;
        }

        // Unconditional by design — no cap check, unlike a plan's level ladder.
        return $this->payouts->record(
            order: $order,
            upline: $upline,
            level: 0,
            baseAmount: $baseAmount,
            amount: $amount,
            percentage: $percentage,
            rankMultiplier: $rankMultiplier,
            planType: Commission::TYPE_SIDELINE_GROWTH_BONUS,
            description: 'sideline growth bonus',
        );
    }

    public function settingsSchema(): array
    {
        return [
            TextInput::make('sideline_growth_bonus_percentage')
                ->label('Sideline growth bonus percentage')
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
            'sideline_growth_bonus_percentage' => (float) SystemSetting::get('sideline_growth_bonus_percentage', 10),
        ];
    }

    public function saveSettings(array $state): void
    {
        $value = $state['sideline_growth_bonus_percentage'] ?? SystemSetting::get('sideline_growth_bonus_percentage', 10);

        SystemSetting::set('sideline_growth_bonus_percentage', (string) $value, 'commission', 'decimal');
    }

    public function dedicatedSettingsPage(): ?string
    {
        return null;
    }
}
