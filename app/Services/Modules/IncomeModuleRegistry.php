<?php

namespace App\Services\Modules;

use Illuminate\Support\Collection;

/**
 * All discovered income modules (Personal Volume today; a future
 * fast-start/matching/leadership bonus tomorrow) — any number can be
 * enabled at once, independent of which PlanModule is active. Found by
 * ModuleDiscovery, same as PlanModuleRegistry, and for the same reason
 * that registry defers instantiation (see its docblock), the constructor
 * here only reads each candidate's static key() too.
 */
class IncomeModuleRegistry
{
    /** @var array<string, class-string<IncomeModule>> */
    private array $classes;

    public function __construct(private readonly ModuleDiscovery $discovery)
    {
        $this->classes = $discovery->discoverClasses(IncomeModule::class)
            ->keyBy(fn (string $class) => $class::key())
            ->all();
    }

    /** @return Collection<int, IncomeModule> */
    public function all(): Collection
    {
        return collect($this->classes)->map(fn (string $class) => $this->discovery->make($class))->values();
    }

    /** @return Collection<int, ScheduledIncomeModule> every enabled scheduled module */
    public function scheduled(): Collection
    {
        return $this->all()
            ->filter(fn (IncomeModule $module) => $module instanceof ScheduledIncomeModule && $module->isEnabled());
    }

    /** @return Collection<int, OrderTriggeredIncomeModule> every enabled order-triggered module */
    public function orderTriggered(): Collection
    {
        return $this->all()
            ->filter(fn (IncomeModule $module) => $module instanceof OrderTriggeredIncomeModule && $module->isEnabled());
    }
}
