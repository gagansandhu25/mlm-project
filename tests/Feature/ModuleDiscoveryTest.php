<?php

namespace Tests\Feature;

use App\Modules\Income\BinaryPairingCommission\BinaryPairingCommissionModule;
use App\Modules\Income\ConfigurableBinaryMatching\ConfigurableBinaryMatchingModule;
use App\Modules\Income\DirectReferralBonus\DirectReferralBonusModule;
use App\Modules\Income\FixedYieldInvestment\FixedYieldInvestmentModule;
use App\Modules\Income\HybridBinaryMatching\HybridBinaryMatchingModule;
use App\Modules\Income\MatrixLevelCommission\MatrixLevelCommissionModule;
use App\Modules\Income\MultiTierReferralBonus\MultiTierReferralBonusModule;
use App\Modules\Income\PersonalVolume\PersonalVolumeModule;
use App\Modules\Income\SidelineGrowthBonus\SidelineGrowthBonusModule;
use App\Modules\Income\UnilevelLevelCommission\UnilevelLevelCommissionModule;
use App\Modules\MatchingBases\Count\CountBasis;
use App\Modules\MatchingBases\Volume\VolumeBasis;
use App\Modules\MatchPayoutFormulas\FlatPerPair\FlatPerPairFormula;
use App\Modules\MatchPayoutFormulas\PercentageOfMatchedVolume\PercentageOfMatchedVolumeFormula;
use App\Modules\MatchQualificationRules\EveryOrder\EveryOrderRule;
use App\Modules\MatchQualificationRules\FirstOrderOnly\FirstOrderOnlyRule;
use App\Modules\PackageResolvers\HighestPackagePurchase\HighestPackagePurchaseResolver;
use App\Modules\PackageResolvers\TotalPackagePurchases\TotalPackagePurchasesResolver;
use App\Modules\Plans\Binary\BinaryModule;
use App\Modules\Plans\Matrix\MatrixModule;
use App\Modules\Plans\Unilevel\UnilevelModule;
use App\Services\Modules\ActivePackageResolverRegistry;
use App\Services\Modules\IncomeModuleRegistry;
use App\Services\Modules\MatchingBasisRegistry;
use App\Services\Modules\MatchPayoutFormulaRegistry;
use App\Services\Modules\MatchQualificationRuleRegistry;
use App\Services\Modules\ModuleDiscovery;
use App\Services\Modules\PlanModuleRegistry;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ModuleDiscoveryTest extends TestCase
{
    public function test_it_discovers_every_shipped_plan_module(): void
    {
        $registry = app(PlanModuleRegistry::class);

        $this->assertInstanceOf(UnilevelModule::class, $registry->for('unilevel'));
        $this->assertInstanceOf(BinaryModule::class, $registry->for('binary'));
        $this->assertInstanceOf(MatrixModule::class, $registry->for('matrix'));
    }

    public function test_it_discovers_every_shipped_income_module(): void
    {
        $registry = app(IncomeModuleRegistry::class);

        $this->assertCount(10, $registry->all());
        $this->assertTrue($registry->all()->contains(fn ($module) => $module instanceof PersonalVolumeModule));
        $this->assertTrue($registry->all()->contains(fn ($module) => $module instanceof DirectReferralBonusModule));
        $this->assertTrue($registry->all()->contains(fn ($module) => $module instanceof MultiTierReferralBonusModule));
        $this->assertTrue($registry->all()->contains(fn ($module) => $module instanceof SidelineGrowthBonusModule));
        $this->assertTrue($registry->all()->contains(fn ($module) => $module instanceof UnilevelLevelCommissionModule));
        $this->assertTrue($registry->all()->contains(fn ($module) => $module instanceof MatrixLevelCommissionModule));
        $this->assertTrue($registry->all()->contains(fn ($module) => $module instanceof BinaryPairingCommissionModule));
        $this->assertTrue($registry->all()->contains(fn ($module) => $module instanceof HybridBinaryMatchingModule));
        $this->assertTrue($registry->all()->contains(fn ($module) => $module instanceof FixedYieldInvestmentModule));
        $this->assertTrue($registry->all()->contains(fn ($module) => $module instanceof ConfigurableBinaryMatchingModule));
    }

    public function test_it_discovers_every_shipped_matching_basis(): void
    {
        $registry = app(MatchingBasisRegistry::class);

        $this->assertCount(2, $registry->all());
        $this->assertInstanceOf(VolumeBasis::class, $registry->for('volume'));
        $this->assertInstanceOf(CountBasis::class, $registry->for('count'));
    }

    public function test_matching_basis_options_lists_every_basis_by_key_and_label(): void
    {
        $options = app(MatchingBasisRegistry::class)->options();

        $this->assertEqualsCanonicalizing([
            'volume' => 'Volume',
            'count' => 'Count',
        ], $options);
    }

    public function test_it_discovers_every_shipped_match_qualification_rule(): void
    {
        $registry = app(MatchQualificationRuleRegistry::class);

        $this->assertCount(2, $registry->all());
        $this->assertInstanceOf(EveryOrderRule::class, $registry->for('every_order'));
        $this->assertInstanceOf(FirstOrderOnlyRule::class, $registry->for('first_order_only'));
    }

    public function test_match_qualification_rule_options_lists_every_rule_by_key_and_label(): void
    {
        $options = app(MatchQualificationRuleRegistry::class)->options();

        $this->assertEqualsCanonicalizing([
            'every_order' => 'Every order',
            'first_order_only' => 'First order only',
        ], $options);
    }

    public function test_it_discovers_every_shipped_match_payout_formula(): void
    {
        $registry = app(MatchPayoutFormulaRegistry::class);

        $this->assertCount(2, $registry->all());
        $this->assertInstanceOf(FlatPerPairFormula::class, $registry->for('flat_per_pair'));
        $this->assertInstanceOf(PercentageOfMatchedVolumeFormula::class, $registry->for('percentage_of_matched_volume'));
    }

    public function test_match_payout_formula_options_lists_every_formula_by_key_and_label(): void
    {
        $options = app(MatchPayoutFormulaRegistry::class)->options();

        $this->assertEqualsCanonicalizing([
            'flat_per_pair' => 'Flat amount per pair',
            'percentage_of_matched_volume' => 'Percentage of matched volume',
        ], $options);
    }

    public function test_it_discovers_every_shipped_active_package_resolver(): void
    {
        $registry = app(ActivePackageResolverRegistry::class);

        $this->assertCount(2, $registry->all());
        $this->assertInstanceOf(HighestPackagePurchaseResolver::class, $registry->for('highest_package_purchase'));
        $this->assertInstanceOf(TotalPackagePurchasesResolver::class, $registry->for('total_package_purchases'));
    }

    public function test_resolver_options_lists_every_resolver_by_key_and_label(): void
    {
        $options = app(ActivePackageResolverRegistry::class)->options();

        $this->assertEqualsCanonicalizing([
            'highest_package_purchase' => 'Highest Package Purchase',
            'total_package_purchases' => 'Total of All Package Purchases',
        ], $options);
    }

    public function test_options_lists_every_plan_module_by_key_and_label(): void
    {
        // Order isn't guaranteed — it follows filesystem directory
        // iteration, not registration order — so compare as a set.
        $options = app(PlanModuleRegistry::class)->options();

        $this->assertEqualsCanonicalizing([
            'unilevel' => 'Unilevel',
            'binary' => 'Binary',
            'matrix' => 'Matrix',
        ], $options);
    }

    public function test_for_throws_for_an_unregistered_plan_type(): void
    {
        $registry = app(PlanModuleRegistry::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown plan type [stairstep].');

        $registry->for('stairstep');
    }

    /**
     * The actual promise this whole system exists for: a brand-new plan
     * dropped into app/Modules/ is discovered with zero edits to any
     * existing file. Creates a real throwaway module folder, proves
     * ModuleDiscovery finds it, then removes it — never leaves it behind.
     */
    public function test_a_new_module_folder_is_discovered_without_touching_any_existing_file(): void
    {
        $dir = app_path('Modules/Plans/TestFixturePlan');

        File::ensureDirectoryExists($dir);
        File::put($dir.'/TestFixturePlanModule.php', $this->fixtureModuleSource());

        try {
            // A fresh discovery/registry, not the cached singleton, so the
            // fixture (created after the container booted) is picked up.
            $registry = new PlanModuleRegistry(new ModuleDiscovery(app()));

            $module = $registry->for('test_fixture_plan');

            $this->assertSame('Test Fixture Plan', $module->label());
        } finally {
            File::deleteDirectory($dir);
        }
    }

    private function fixtureModuleSource(): string
    {
        return <<<'PHP'
        <?php

        namespace App\Modules\Plans\TestFixturePlan;

        use App\Services\Modules\PlanModule;
        use App\Services\Placement\PlacementStrategyInterface;

        class TestFixturePlanModule implements PlanModule
        {
            public static function key(): string { return 'test_fixture_plan'; }
            public function label(): string { return 'Test Fixture Plan'; }
            public function description(): string { return ''; }
            public function placementStrategy(): PlacementStrategyInterface { throw new \RuntimeException('not needed for this test'); }
            public function settingsSchema(): array { return []; }
            public function settingsData(): array { return []; }
            public function saveSettings(array $state): void {}
            public function dedicatedSettingsPage(): ?string { return null; }
        }
        PHP;
    }
}
