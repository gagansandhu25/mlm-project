<?php

namespace App\Modules\Plans\Binary;

use App\Models\Commission;
use App\Models\User;
use App\Services\Placement\PlacementResult;
use App\Services\Placement\PlacementStrategyInterface;

/**
 * Binary trees allow exactly two children (left/right) per node. New
 * recruits spill over to the shallowest open left/right slot beneath
 * the sponsor (breadth-first), so legs fill out evenly.
 */
class BinaryPlacementStrategy implements PlacementStrategyInterface
{
    public function planType(): string
    {
        return Commission::TYPE_BINARY;
    }

    public function findPlacement(User $sponsor): PlacementResult
    {
        $queue = [$sponsor];

        while ($queue) {
            /** @var User $node */
            $node = array_shift($queue);

            $children = $node->children()->get()->keyBy('position');

            if (! $children->has(User::POSITION_LEFT)) {
                return new PlacementResult($node, User::POSITION_LEFT);
            }

            if (! $children->has(User::POSITION_RIGHT)) {
                return new PlacementResult($node, User::POSITION_RIGHT);
            }

            $queue[] = $children->get(User::POSITION_LEFT);
            $queue[] = $children->get(User::POSITION_RIGHT);
        }

        throw new \RuntimeException('Unable to find an open binary slot beneath sponsor #'.$sponsor->id);
    }
}
