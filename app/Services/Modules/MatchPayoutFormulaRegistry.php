<?php

namespace App\Services\Modules;

use Illuminate\Support\Collection;

/**
 * All discovered MatchPayoutFormula strategies — same shape as
 * ActivePackageResolverRegistry / MatchingBasisRegistry. Found by
 * ModuleDiscovery under app/Modules/MatchPayoutFormulas/.
 */
class MatchPayoutFormulaRegistry
{
    /** @var array<string, class-string<MatchPayoutFormula>> */
    private array $classes;

    public function __construct(private readonly ModuleDiscovery $discovery)
    {
        $this->classes = $discovery->discoverClasses(MatchPayoutFormula::class, 'Formula')
            ->keyBy(fn (string $class) => $class::key())
            ->all();
    }

    public function for(string $key): MatchPayoutFormula
    {
        $class = $this->classes[$key]
            ?? throw new \InvalidArgumentException("Unknown match payout formula [{$key}].");

        return $this->discovery->make($class);
    }

    /** @return Collection<int, MatchPayoutFormula> */
    public function all(): Collection
    {
        return collect($this->classes)->map(fn (string $class) => $this->discovery->make($class))->values();
    }

    /** @return array<string, string> key => label, for a Filament Select */
    public function options(): array
    {
        return $this->all()->mapWithKeys(fn (MatchPayoutFormula $formula) => [$formula::key() => $formula->label()])->all();
    }
}
