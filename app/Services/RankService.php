<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Rank;
use App\Models\User;

/**
 * Evaluates whether a user qualifies for a higher rank based on their
 * team's total sales volume and downline size, applying rank-up
 * immediately when a new threshold is reached (STEP 9 of the
 * commission workflow).
 */
class RankService
{
    public function __construct(private readonly TreeService $tree) {}

    public function evaluate(User $user): ?Rank
    {
        $teamVolume = $this->tree->getTeamVolume($user) + (float) $user->sales_volume;
        $downlineCount = $this->tree->getTotalDownline($user);

        $qualifyingRank = Rank::query()
            ->where('is_active', true)
            ->where('min_sales_volume', '<=', $teamVolume)
            ->where('min_downline', '<=', $downlineCount)
            ->orderByDesc('level')
            ->first();

        if (! $qualifyingRank || $qualifyingRank->id === $user->rank_id) {
            return $qualifyingRank;
        }

        $previousRankId = $user->rank_id;
        $user->rank_id = $qualifyingRank->id;
        $user->save();

        ActivityLog::log(
            action: 'rank.upgraded',
            description: "User #{$user->id} reached rank [{$qualifyingRank->name}].",
            userId: $user->id,
            old: ['rank_id' => $previousRankId],
            new: ['rank_id' => $qualifyingRank->id],
        );

        return $qualifyingRank;
    }
}
