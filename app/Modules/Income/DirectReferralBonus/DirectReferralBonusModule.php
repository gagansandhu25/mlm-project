<?php

namespace App\Modules\Income\DirectReferralBonus;

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
 * Pays the buyer's direct sponsor a flat, unconditional percentage on
 * every completed order — independent of whichever PlanModule is
 * active, so it can be layered on top of Unilevel, Binary, Matrix, or
 * Package Tier alike. Originally built into Package Tier specifically;
 * moved here once it became clear the "flat direct-sponsor reward"
 * concern has nothing to do with any one plan's placement/tier shape.
 */
class DirectReferralBonusModule implements OrderTriggeredIncomeModule
{
    public function __construct(
        private readonly TreeService $tree,
        private readonly CommissionPayoutRecorder $payouts,
    ) {}

    public static function key(): string
    {
        return 'direct_referral_bonus';
    }

    public function label(): string
    {
        return 'Direct Referral Bonus';
    }

    public function description(): string
    {
        return 'Pays the buyer\'s direct sponsor a flat, unconditional percentage on every completed order — independent of whichever plan is active.';
    }

    public function isEnabled(): bool
    {
        return filter_var(SystemSetting::get('direct_referral_bonus_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function setEnabled(bool $enabled): void
    {
        SystemSetting::set('direct_referral_bonus_enabled', $enabled ? 'true' : 'false', 'commission', 'boolean');
    }

    /** @return Collection<int, Commission> */
    public function handle(Order $order): Collection
    {
        $commission = $this->payDirectSponsor($order);

        return $commission ? new Collection([$commission]) : new Collection;
    }

    public function settingsSchema(): array
    {
        return [
            TextInput::make('direct_referral_bonus_percentage')
                ->label('Direct referral bonus percentage')
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
            'direct_referral_bonus_percentage' => (float) SystemSetting::get('direct_referral_bonus_percentage', 5),
        ];
    }

    public function saveSettings(array $state): void
    {
        $value = $state['direct_referral_bonus_percentage'] ?? SystemSetting::get('direct_referral_bonus_percentage', 5);

        SystemSetting::set('direct_referral_bonus_percentage', (string) $value, 'commission', 'decimal');
    }

    public function dedicatedSettingsPage(): ?string
    {
        return null;
    }

    private function payDirectSponsor(Order $order): ?Commission
    {
        $sponsor = $this->tree->getAncestorsByLevel($order->user, 1)[1] ?? null;

        if (! $sponsor || $sponsor->status !== User::STATUS_ACTIVE) {
            return null;
        }

        $percentage = (float) SystemSetting::get('direct_referral_bonus_percentage', 5);
        $baseAmount = round((float) $order->commission_value * ($percentage / 100), 2);

        if ($baseAmount <= 0) {
            return null;
        }

        $rankMultiplier = (float) ($sponsor->rank?->commission_multiplier ?? 1.0);
        $amount = round($baseAmount * $rankMultiplier, 2);

        if ($amount <= 0) {
            return null;
        }

        // Unconditional by design — no cap check, unlike a plan's level ladder.
        return $this->payouts->record(
            order: $order,
            upline: $sponsor,
            level: 0,
            baseAmount: $baseAmount,
            amount: $amount,
            percentage: $percentage,
            rankMultiplier: $rankMultiplier,
            planType: Commission::TYPE_DIRECT_REFERRAL_BONUS,
            description: 'direct referral bonus',
        );
    }
}
