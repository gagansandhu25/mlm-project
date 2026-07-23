<?php

namespace App\Services\Modules;

use Illuminate\Support\Collection;

/**
 * All discovered ActivePackageResolver strategies — exactly one is
 * active at a time (chosen per-module, e.g. HybridBinaryMatchingModule's
 * own settings), same shape as PlanModuleRegistry. Found by
 * ModuleDiscovery under app/Modules/PackageResolvers/, and for the same
 * reason PlanModuleRegistry defers instantiation (see its docblock),
 * the constructor here only reads each candidate's static key() too.
 */
class ActivePackageResolverRegistry
{
    /** @var array<string, class-string<ActivePackageResolver>> */
    private array $classes;

    public function __construct(private readonly ModuleDiscovery $discovery)
    {
        $this->classes = $discovery->discoverClasses(ActivePackageResolver::class, 'Resolver')
            ->keyBy(fn (string $class) => $class::key())
            ->all();
    }

    public function for(string $key): ActivePackageResolver
    {
        $class = $this->classes[$key]
            ?? throw new \InvalidArgumentException("Unknown active package resolver [{$key}].");

        return $this->discovery->make($class);
    }

    /** @return Collection<int, ActivePackageResolver> */
    public function all(): Collection
    {
        return collect($this->classes)->map(fn (string $class) => $this->discovery->make($class))->values();
    }

    /** @return array<string, string> key => label, for a Filament Select */
    public function options(): array
    {
        return $this->all()->mapWithKeys(fn (ActivePackageResolver $resolver) => [$resolver::key() => $resolver->label()])->all();
    }
}
