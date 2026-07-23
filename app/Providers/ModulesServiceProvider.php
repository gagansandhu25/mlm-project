<?php

namespace App\Providers;

use App\Services\Modules\ActivePackageResolverRegistry;
use App\Services\Modules\IncomeModuleRegistry;
use App\Services\Modules\MatchingBasisRegistry;
use App\Services\Modules\MatchPayoutFormulaRegistry;
use App\Services\Modules\MatchQualificationRuleRegistry;
use App\Services\Modules\ModuleDiscovery;
use App\Services\Modules\PlanModuleRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Binds the plan/income/resolver module registries. All are built by
 * scanning app/Modules/ at construction time (see ModuleDiscovery) —
 * adding a new plan, bonus, or resolver type never means editing this
 * provider, unlike the hardcoded-array registries it replaces.
 */
class ModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PlanModuleRegistry::class, fn ($app) => new PlanModuleRegistry(
            new ModuleDiscovery($app)
        ));

        $this->app->singleton(IncomeModuleRegistry::class, fn ($app) => new IncomeModuleRegistry(
            new ModuleDiscovery($app)
        ));

        $this->app->singleton(ActivePackageResolverRegistry::class, fn ($app) => new ActivePackageResolverRegistry(
            new ModuleDiscovery($app)
        ));

        $this->app->singleton(MatchingBasisRegistry::class, fn ($app) => new MatchingBasisRegistry(
            new ModuleDiscovery($app)
        ));

        $this->app->singleton(MatchQualificationRuleRegistry::class, fn ($app) => new MatchQualificationRuleRegistry(
            new ModuleDiscovery($app)
        ));

        $this->app->singleton(MatchPayoutFormulaRegistry::class, fn ($app) => new MatchPayoutFormulaRegistry(
            new ModuleDiscovery($app)
        ));
    }
}
