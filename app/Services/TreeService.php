<?php

namespace App\Services;

use App\Models\Downline;
use App\Models\User;
use App\Services\Modules\PlanModuleRegistry;
use App\Services\Placement\PlacementStrategyInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Implements the closure-table pattern for the genealogy tree: every
 * ancestor/descendant pair (including a self row at depth 0) is a row
 * in `downlines`, keyed by (ancestor_id, descendant_id) with a
 * `depth` distance. Descendant/ancestor lookups are indexed joins
 * instead of recursive CTEs or path string scans, and re-parenting a
 * subtree (not yet implemented) only touches the moved subtree's
 * rows rather than every descendant's denormalized path string.
 */
class TreeService
{
    public function __construct(
        private readonly PlanModuleRegistry $modules,
    ) {}

    public function placementStrategyFor(string $planType): PlacementStrategyInterface
    {
        return $this->modules->for($planType)->placementStrategy();
    }

    /**
     * Place an unsaved $newUser beneath $sponsor according to the
     * active plan type's placement rules, persisting parent_id/depth
     * and the new closure rows.
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

            Downline::create([
                'ancestor_id' => $newUser->id,
                'descendant_id' => $newUser->id,
                'depth' => 0,
            ]);

            // Copy the parent's own closure rows (its self row plus every
            // ancestor row) shifted one level deeper — the standard
            // closure-table single-node insert.
            DB::table('downlines')->insertUsing(
                ['ancestor_id', 'descendant_id', 'depth'],
                DB::table('downlines')
                    ->select('ancestor_id', DB::raw((int) $newUser->id), DB::raw('depth + 1'))
                    ->where('descendant_id', $placement->parent->id)
            );

            return $newUser;
        });
    }

    /**
     * Establish $user as the root of a new, independent tree: just the
     * closure self-row, no ancestors. Idempotent — safe to call again
     * for a user that's already rooted (e.g. re-running a seeder).
     */
    public function placeRoot(User $user): User
    {
        Downline::query()->firstOrCreate([
            'ancestor_id' => $user->id,
            'descendant_id' => $user->id,
        ], [
            'depth' => 0,
        ]);

        return $user;
    }

    /** Whether $descendant is a genuine descendant of $ancestor (or the same user). */
    public function isDescendantOf(User $descendant, User $ancestor): bool
    {
        return Downline::query()
            ->where('ancestor_id', $ancestor->id)
            ->where('descendant_id', $descendant->id)
            ->exists();
    }

    /** All ancestors (uplines) of $user, ordered root-first. */
    public function getAncestors(User $user): Collection
    {
        return User::query()
            ->join('downlines', 'users.id', '=', 'downlines.ancestor_id')
            ->where('downlines.descendant_id', $user->id)
            ->where('downlines.depth', '>', 0)
            ->orderBy('users.depth')
            ->select('users.*')
            ->get();
    }

    /**
     * Ancestors keyed by level (1 = direct parent, 2 = grandparent, ...),
     * capped at $maxLevel. This is the lookup the commission engine uses.
     *
     * @return array<int, User>
     */
    public function getAncestorsByLevel(User $user, int $maxLevel): array
    {
        $rows = Downline::query()
            ->with('ancestor')
            ->where('descendant_id', $user->id)
            ->whereBetween('depth', [1, $maxLevel])
            ->orderBy('depth')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->depth] = $row->ancestor;
        }

        return $result;
    }

    /** All descendants of $user (the entire downline). */
    public function getDescendants(User $user): Collection
    {
        return User::query()
            ->join('downlines', 'users.id', '=', 'downlines.descendant_id')
            ->where('downlines.ancestor_id', $user->id)
            ->where('downlines.depth', '>', 0)
            ->select('users.*')
            ->get();
    }

    /** Direct children only. */
    public function getChildren(User $user): Collection
    {
        return $user->children()->get();
    }

    /** Descendants exactly $level generations below $user. */
    public function getDownlineByLevel(User $user, int $level): Collection
    {
        if ($level < 1) {
            return new Collection;
        }

        return User::query()
            ->join('downlines', 'users.id', '=', 'downlines.descendant_id')
            ->where('downlines.ancestor_id', $user->id)
            ->where('downlines.depth', $level)
            ->select('users.*')
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
        return (float) DB::table('downlines')
            ->join('users', 'users.id', '=', 'downlines.descendant_id')
            ->where('downlines.ancestor_id', $user->id)
            ->where('downlines.depth', '>', 0)
            ->sum('users.sales_volume');
    }

    /** Total count of descendants. */
    public function getTotalDownline(User $user): int
    {
        return Downline::query()
            ->where('ancestor_id', $user->id)
            ->where('depth', '>', 0)
            ->count();
    }

    /** Users directly recruited (sponsored) by $user, regardless of tree placement. */
    public function getDirectSponsors(User $user): Collection
    {
        return $user->referrals()->get();
    }

    /**
     * $user's 1-indexed position among their own parent's direct
     * children, ordered by id (insertion/placement order) — deliberately
     * independent of the plan-specific `position` column, whose meaning
     * differs per plan (null for Unilevel, left/right for Binary, a
     * numeric string for Matrix). Null for a user with no parent (root).
     */
    public function siblingRank(User $user): ?int
    {
        if ($user->parent_id === null) {
            return null;
        }

        $rank = User::query()
            ->where('parent_id', $user->parent_id)
            ->orderBy('id')
            ->pluck('id')
            ->search($user->id);

        return $rank === false ? null : $rank + 1;
    }
}
