<?php

namespace App\Services\Placement;

use App\Models\User;

/**
 * Matrix trees have a fixed width (e.g. 3x9): each node may hold at
 * most `$width` children. New recruits spill over breadth-first to
 * the first node beneath the sponsor with an open slot.
 */
class MatrixPlacementStrategy implements PlacementStrategyInterface
{
    public function __construct(private readonly int $width = 3) {}

    public function findPlacement(User $sponsor): PlacementResult
    {
        $queue = [$sponsor];

        while ($queue) {
            /** @var User $node */
            $node = array_shift($queue);

            $children = $node->children()->orderBy('position')->get();

            if ($children->count() < $this->width) {
                return new PlacementResult($node, (string) ($children->count() + 1));
            }

            foreach ($children as $child) {
                $queue[] = $child;
            }
        }

        throw new \RuntimeException('Unable to find an open matrix slot beneath sponsor #'.$sponsor->id);
    }
}
