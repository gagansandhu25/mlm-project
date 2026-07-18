<?php

namespace App\Services;

use App\Models\User;
use App\Services\Placement\PlacementStrategyInterface;
use App\Services\Placement\PlacementStrategyRegistry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Implements the materialized path pattern for the genealogy tree:
 * `path` holds the dash-free chain of ancestor ids (e.g. "1/2/5/12")
 * and `depth` is the number of ancestors. Both are indexed, so
 * descendant/ancestor lookups are simple LIKE/IN queries instead of
 * recursive CTEs.
 */
class TreeService
{
    public function __construct(
        private readonly PlacementStrategyRegistry $placementStrategies,
    ) {}

    public function placementStrategyFor(string $planType): PlacementStrategyInterface
    {
        return $this->placementStrategies->for($planType);
    }

    /**
     * Place an unsaved $newUser beneath $sponsor according to the
     * active plan type's placement rules, persisting path/depth/position.
     */
    public function placeNewUser(User $newUser, User $sponsor, string $planType): User
    {
        return DB::transaction(function () use ($newUser, $sponsor, $planType) {
            $placement = $this->placementStrategyFor($planType)->findPlacement($sponsor);

            $newUser->sponsor_id ??= $sponsor->id;
            $newUser->parent_id = $placement->parent->id;
            $newUser->position = $placement->position;
            $newUser->depth = $placement->parent->depth + 1;
            $newUser->save();

            $newUser->path = $placement->parent->path
                ? "{$placement->parent->path}/{$newUser->id}"
                : (string) $newUser->id;
            $newUser->save();

            return $newUser;
        });
    }

    /** All ancestors (uplines) of $user, ordered root-first. */
    public function getAncestors(User $user): Collection
    {
        $ids = $this->ancestorIdsClosestFirst($user);

        if (empty($ids)) {
            return new Collection;
        }

        return User::query()->whereIn('id', $ids)->orderBy('depth')->get();
    }

    /**
     * Ancestors keyed by level (1 = direct parent, 2 = grandparent, ...),
     * capped at $maxLevel. This is the lookup the commission engine uses.
     *
     * @return array<int, User>
     */
    public function getAncestorsByLevel(User $user, int $maxLevel): array
    {
        $ids = array_slice($this->ancestorIdsClosestFirst($user), 0, $maxLevel);

        if (empty($ids)) {
            return [];
        }

        $usersById = User::query()->whereIn('id', $ids)->get()->keyBy('id');

        $result = [];
        foreach ($ids as $index => $id) {
            if ($usersById->has($id)) {
                $result[$index + 1] = $usersById->get($id);
            }
        }

        return $result;
    }

    /** All descendants of $user (the entire downline). */
    public function getDescendants(User $user): Collection
    {
        if (! $user->path) {
            return new Collection;
        }

        return User::query()->where('path', 'like', "{$user->path}/%")->get();
    }

    /** Direct children only. */
    public function getChildren(User $user): Collection
    {
        return $user->children()->get();
    }

    /** Descendants exactly $level generations below $user. */
    public function getDownlineByLevel(User $user, int $level): Collection
    {
        if (! $user->path || $level < 1) {
            return new Collection;
        }

        return User::query()
            ->where('path', 'like', "{$user->path}/%")
            ->where('depth', $user->depth + $level)
            ->get();
    }

    /** Entire left leg (binary), including the left child itself. */
    public function getLeftLeg(User $user): Collection
    {
        return $this->getLeg($user, User::POSITION_LEFT);
    }

    /** Entire right leg (binary), including the right child itself. */
    public function getRightLeg(User $user): Collection
    {
        return $this->getLeg($user, User::POSITION_RIGHT);
    }

    private function getLeg(User $user, string $position): Collection
    {
        $child = $user->children()->where('position', $position)->first();

        if (! $child) {
            return new Collection;
        }

        return $this->getDescendants($child)->prepend($child);
    }

    /** Sum of sales_volume across the user's entire downline. */
    public function getTeamVolume(User $user): float
    {
        if (! $user->path) {
            return 0.0;
        }

        return (float) User::query()->where('path', 'like', "{$user->path}/%")->sum('sales_volume');
    }

    /** Total count of descendants. */
    public function getTotalDownline(User $user): int
    {
        if (! $user->path) {
            return 0;
        }

        return User::query()->where('path', 'like', "{$user->path}/%")->count();
    }

    /** Users directly recruited (sponsored) by $user, regardless of tree placement. */
    public function getDirectSponsors(User $user): Collection
    {
        return $user->referrals()->get();
    }

    /**
     * @return list<int> ancestor ids, closest ancestor first, self excluded.
     */
    private function ancestorIdsClosestFirst(User $user): array
    {
        if (! $user->path) {
            return [];
        }

        $ids = array_map('intval', explode('/', $user->path));
        array_pop($ids); // drop self

        return array_reverse($ids);
    }
}
