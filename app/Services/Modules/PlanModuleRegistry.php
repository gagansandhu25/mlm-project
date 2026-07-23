<?php

namespace App\Services\Modules;

use Illuminate\Support\Collection;

/**
 * Resolves the PlanModule for a given `active_plan_type` value. Modules
 * are found by ModuleDiscovery, not registered by hand here — adding a
 * plan means adding a folder under app/Modules/, never editing this class.
 *
 * The constructor only reads each candidate class's static key() — it
 * does not instantiate any module. Several modules' commission
 * calculators depend on TreeService, which itself depends on this
 * registry; eagerly building every module here would recurse infinitely.
 * Actual instances are built lazily in for()/all().
 */
class PlanModuleRegistry
{
    /** @var array<string, class-string<PlanModule>> */
    private array $classes;

    public function __construct(private readonly ModuleDiscovery $discovery)
    {
        $this->classes = $discovery->discoverClasses(PlanModule::class)
            ->keyBy(fn (string $class) => $class::key())
            ->all();
    }

    public function for(string $key): PlanModule
    {
        $class = $this->classes[$key]
            ?? throw new \InvalidArgumentException("Unknown plan type [{$key}].");

        return $this->discovery->make($class);
    }

    /** @return Collection<int, PlanModule> */
    public function all(): Collection
    {
        return collect($this->classes)->map(fn (string $class) => $this->discovery->make($class))->values();
    }

    /** @return array<string, string> key => label, for a Filament Select */
    public function options(): array
    {
        return $this->all()->mapWithKeys(fn (PlanModule $module) => [$module::key() => $module->label()])->all();
    }
}
