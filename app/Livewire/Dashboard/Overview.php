<?php

namespace App\Livewire\Dashboard;

use App\Models\Rank;
use App\Services\TreeService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Overview extends Component
{
    public function render(): View
    {
        $user = auth()->user();
        $tree = app(TreeService::class);

        $nextRank = Rank::query()
            ->where('is_active', true)
            ->where('level', '>', $user->rank?->level ?? 0)
            ->orderBy('level')
            ->first();

        $teamVolume = $tree->getTeamVolume($user) + (float) $user->sales_volume;
        $downlineCount = $tree->getTotalDownline($user);

        return view('livewire.dashboard.overview', [
            'wallet' => $user->wallet,
            'rank' => $user->rank,
            'nextRank' => $nextRank,
            'teamVolume' => $teamVolume,
            'downlineCount' => $downlineCount,
            'volumeProgress' => $nextRank && $nextRank->min_sales_volume > 0
                ? min(100, (int) round($teamVolume / $nextRank->min_sales_volume * 100))
                : 100,
            'downlineProgress' => $nextRank && $nextRank->min_downline > 0
                ? min(100, (int) round($downlineCount / $nextRank->min_downline * 100))
                : 100,
            'directSponsors' => $tree->getDirectSponsors($user)->count(),
            'recentCommissions' => $user->commissionsEarned()->latest('calculated_at')->limit(5)->get(),
            'referralUrl' => route('register', ['ref' => $user->referral_code]),
        ]);
    }
}
