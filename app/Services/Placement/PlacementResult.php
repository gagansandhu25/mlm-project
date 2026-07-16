<?php

namespace App\Services\Placement;

use App\Models\User;

final class PlacementResult
{
    public function __construct(
        public readonly User $parent,
        public readonly ?string $position = null,
    ) {}
}
