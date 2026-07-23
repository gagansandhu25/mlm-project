<?php

namespace App\Services\Modules;

use Illuminate\Support\Collection;

/**
 * All discovered MatchingBasis strategies — exactly one is active at a
 * time, chosen from ConfigurableBinaryMatchingModule's own settings.
 * Found by ModuleDiscovery under app/Modules/MatchingBases/, same shape
 * as ActivePackageResolverRegistry (constructor only reads each
 * candidate's static key(), instantiation deferred to for()/all()).
 */
class MatchingBasisRegistry
{
    /** @var array<string, class-string<MatchingBasis>> */
    private array $classes;

    public function __construct(private readonly ModuleDiscovery $discovery)
    {
        $this->classes = $discovery->discoverClasses(MatchingBasis::class, 'Basis')
            ->keyBy(fn (string $class) => $class::key())
            ->all();
    }

    public function for(string $key): MatchingBasis
    {
        $class = $this->classes[$key]
            ?? throw new \InvalidArgumentException("Unknown matching basis [{$key}].");

        return $this->discovery->make($class);
    }

    /** @return Collection<int, MatchingBasis> */
    public function all(): Collection
    {
        return collect($this->classes)->map(fn (string $class) => $this->discovery->make($class))->values();
    }

    /** @return array<string, string> key => label, for a Filament Select */
    public function options(): array
    {
        return $this->all()->mapWithKeys(fn (MatchingBasis $basis) => [$basis::key() => $basis->label()])->all();
    }
}
