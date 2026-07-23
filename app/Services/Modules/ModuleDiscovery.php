<?php

namespace App\Services\Modules;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Finds plan/income/resolver modules by convention, not configuration:
 * any folder under app/Modules/{Root}/{Name}/ containing a class
 * App\Modules\{Root}\{Name}\{Name}{Suffix} that implements the requested
 * interface is discovered automatically. The roots are purely
 * organizational (so the folder listing tells you at a glance which
 * modules are compensation plans, stackable bonuses, or resolvers) —
 * every root is scanned for every interface, so nothing enforces a
 * module living in "the right" root beyond convention. $suffix defaults
 * to "Module" (what PlanModule/IncomeModule discovery uses); a smaller,
 * differently-shaped concept like ActivePackageResolver passes its own
 * suffix instead, so its classes read as `{Name}Resolver`, not
 * `{Name}Module`, without needing a second discovery mechanism.
 *
 * A live directory scan, not a cached manifest — this app boots the whole
 * framework fresh on every request anyway, and scanning a handful of
 * module folders costs low single-digit milliseconds, so there's nothing
 * to invalidate and no build step a module author needs to run.
 *
 * Deliberately returns class names, not instances: a registry needs every
 * module's key() up front to build its lookup map, but actually
 * instantiating a module (which may pull in TreeService et al.) has to
 * stay lazy — see PlanModule::key()'s docblock for why eager instantiation
 * here would recurse.
 */
class ModuleDiscovery
{
    private const ROOTS = ['Plans', 'Income', 'PackageResolvers', 'MatchingBases', 'MatchQualificationRules', 'MatchPayoutFormulas'];

    public function __construct(private readonly Container $app) {}

    /**
     * @param  class-string  $interface
     * @return Collection<int, class-string>
     */
    public function discoverClasses(string $interface, string $suffix = 'Module'): Collection
    {
        return collect(self::ROOTS)
            ->flatMap(fn (string $namespace) => $this->scan($namespace, $suffix))
            ->filter(fn (string $class) => class_exists($class) && in_array($interface, class_implements($class) ?: []))
            ->values();
    }

    /** @return Collection<int, class-string> */
    private function scan(string $namespace, string $suffix): Collection
    {
        $root = app_path("Modules/{$namespace}");

        if (! File::isDirectory($root)) {
            return collect();
        }

        return collect(File::directories($root))
            ->map(fn (string $dir) => basename($dir))
            ->map(fn (string $name) => "App\\Modules\\{$namespace}\\{$name}\\{$name}{$suffix}");
    }

    /**
     * @template T
     *
     * @param  class-string<T>  $class
     * @return T
     */
    public function make(string $class): object
    {
        return $this->app->make($class);
    }
}
