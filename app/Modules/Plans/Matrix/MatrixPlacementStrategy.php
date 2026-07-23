<?php

namespace App\Modules\Plans\Matrix;

use App\Models\Commission;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Placement\PlacementResult;
use App\Services\Placement\PlacementStrategyInterface;

/**
 * Matrix trees have a fixed width (e.g. 3x9): each node may hold at
 * most `$width` children. New recruits spill over breadth-first to
 * the first node beneath the sponsor with an open slot.
 */
class MatrixPlacementStrategy implements PlacementStrategyInterface
{
    public function planType(): string
    {
        return Commission::TYPE_MATRIX;
    }

    public function findPlacement(User $sponsor): PlacementResult
    {
        // Read fresh per call, not baked into the constructor — this
        // strategy is a long-lived singleton (resolved once via
        // MatrixModule/PlanModuleRegistry), so if matrix_width were
        // captured at construction time, a long-running process would
        // keep placing recruits under a stale width even after an admin
        // changed it.
        $width = (int) SystemSetting::get('matrix_width', 3);

        $queue = [$sponsor];

        while ($queue) {
            /** @var User $node */
            $node = array_shift($queue);

            $children = $node->children()->orderBy('position')->get();

            if ($children->count() < $width) {
                return new PlacementResult($node, (string) ($children->count() + 1));
            }

            foreach ($children as $child) {
                $queue[] = $child;
            }
        }

        throw new \RuntimeException('Unable to find an open matrix slot beneath sponsor #'.$sponsor->id);
    }
}
