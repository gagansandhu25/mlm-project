<?php

namespace App\Livewire\Dashboard;

use App\Models\User;
use App\Services\TreeService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * A drill-down genealogy explorer: unlike the accordion-style tree on the
 * Network page, this recenters on whichever member you click into,
 * showing their business stats and their direct downline, with a
 * breadcrumb trail back up to you.
 */
#[Layout('layouts.app')]
class Genealogy extends Component
{
    #[Url(as: 'member')]
    public ?int $focusUserId = null;

    public function mount(): void
    {
        $this->resolveFocus();
    }

    public function focus(int $userId): void
    {
        $this->focusUserId = $userId;
        $this->resolveFocus();
    }

    public function render(): View
    {
        $tree = app(TreeService::class);
        $focus = $this->focusUser();

        return view('livewire.dashboard.genealogy', [
            'focus' => $this->nodeStats($focus, $tree),
            'breadcrumbs' => $this->breadcrumbs($focus, $tree),
            'children' => $tree->getChildren($focus)->load('rank')
                ->map(fn (User $child) => $this->nodeStats($child, $tree))
                ->all(),
        ]);
    }

    /**
     * Keeps $focusUserId honest: always self, or a genuine descendant of
     * self — never an arbitrary member elsewhere in the company, even if
     * someone hand-edits the `member` query string.
     */
    private function resolveFocus(): void
    {
        if ($this->focusUserId === null) {
            return;
        }

        $self = auth()->user();

        if ($this->focusUserId === $self->id) {
            $this->focusUserId = null;

            return;
        }

        $candidate = User::find($this->focusUserId);

        if (! $candidate || ! $candidate->path || ! $self->path || ! str_starts_with($candidate->path, "{$self->path}/")) {
            $this->focusUserId = null;
        }
    }

    private function focusUser(): User
    {
        return $this->focusUserId
            ? User::with('rank')->findOrFail($this->focusUserId)
            : auth()->user()->load('rank');
    }

    /** @return array<int, array{id: int, name: string}> */
    private function breadcrumbs(User $focus, TreeService $tree): array
    {
        $self = auth()->user();

        if ($focus->id === $self->id) {
            return [];
        }

        return $tree->getAncestors($focus)
            ->filter(fn (User $ancestor) => $ancestor->depth >= $self->depth)
            ->push($focus)
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function nodeStats(User $user, TreeService $tree): array
    {
        $leftLeg = $tree->getLeftLeg($user);
        $rightLeg = $tree->getRightLeg($user);

        return [
            'user' => $user,
            'teamVolume' => $tree->getTeamVolume($user),
            'downlineCount' => $tree->getTotalDownline($user),
            'leftLegCount' => $leftLeg->count(),
            'leftLegVolume' => (float) $leftLeg->sum('sales_volume'),
            'rightLegCount' => $rightLeg->count(),
            'rightLegVolume' => (float) $rightLeg->sum('sales_volume'),
        ];
    }
}
