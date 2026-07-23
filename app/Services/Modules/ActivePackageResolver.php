<?php

namespace App\Services\Modules;

use App\Models\User;

/**
 * Resolves a user's "active package" value — the figure daily/lifetime
 * matching caps get multiplied against (e.g. HybridBinaryMatchingModule).
 * Different companies define this differently (highest-ever package
 * purchase, total of all package purchases, ...), so it's pluggable the
 * same way a plan or income module is: drop a new
 * app/Modules/PackageResolvers/{Name}/{Name}Resolver.php implementing
 * this interface, and it's discovered automatically — nothing outside
 * that folder needs to change.
 */
interface ActivePackageResolver
{
    /** Static for the same reason as PlanModule::key() — see its docblock. */
    public static function key(): string;

    /** Human-facing name for the resolver-picker dropdown. */
    public function label(): string;

    public function resolve(User $user): float;
}
