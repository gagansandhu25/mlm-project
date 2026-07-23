<?php

namespace App\Services\Modules;

use Illuminate\Support\Collection;

/**
 * All discovered MatchQualificationRule strategies — same shape as
 * ActivePackageResolverRegistry / MatchingBasisRegistry. Found by
 * ModuleDiscovery under app/Modules/MatchQualificationRules/.
 */
class MatchQualificationRuleRegistry
{
    /** @var array<string, class-string<MatchQualificationRule>> */
    private array $classes;

    public function __construct(private readonly ModuleDiscovery $discovery)
    {
        $this->classes = $discovery->discoverClasses(MatchQualificationRule::class, 'Rule')
            ->keyBy(fn (string $class) => $class::key())
            ->all();
    }

    public function for(string $key): MatchQualificationRule
    {
        $class = $this->classes[$key]
            ?? throw new \InvalidArgumentException("Unknown match qualification rule [{$key}].");

        return $this->discovery->make($class);
    }

    /** @return Collection<int, MatchQualificationRule> */
    public function all(): Collection
    {
        return collect($this->classes)->map(fn (string $class) => $this->discovery->make($class))->values();
    }

    /** @return array<string, string> key => label, for a Filament Select */
    public function options(): array
    {
        return $this->all()->mapWithKeys(fn (MatchQualificationRule $rule) => [$rule::key() => $rule->label()])->all();
    }
}
