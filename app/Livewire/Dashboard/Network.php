<?php

namespace App\Livewire\Dashboard;

use App\Models\User;
use App\Services\TreeService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Network extends Component
{
    /** @var array<int, bool> User ids currently expanded, keyed by id. */
    public array $expanded = [];

    public function mount(): void
    {
        $this->expanded[auth()->id()] = true;
    }

    public function toggle(int $userId): void
    {
        if (isset($this->expanded[$userId])) {
            unset($this->expanded[$userId]);
        } else {
            $this->expanded[$userId] = true;
        }
    }

    public function render(): View
    {
        return view('livewire.dashboard.network', [
            'tree' => $this->buildTree(auth()->user()),
        ]);
    }

    /**
     * Recursively builds a plain-array representation of the visible
     * portion of the tree, only descending into nodes the user has
     * toggled open — collapsed branches never hit the database.
     */
    private function buildTree(User $node): array
    {
        $isExpanded = (bool) ($this->expanded[$node->id] ?? false);
        $children = app(TreeService::class)->getChildren($node)->load('rank');

        return [
            'user' => $node,
            'hasChildren' => $children->isNotEmpty(),
            'expanded' => $isExpanded,
            'children' => $isExpanded
                ? $children->map(fn (User $child) => $this->buildTree($child))->all()
                : [],
        ];
    }
}
