<div class="max-w-5xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
    <x-card>
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">My Network</h2>
                <p class="text-sm text-gray-500 mt-1">Click + to expand a member's direct downline.</p>
            </div>
            <a href="{{ route('genealogy') }}" wire:navigate class="text-sm text-amber-600 hover:text-amber-700 font-medium whitespace-nowrap">
                Genealogy view &rarr;
            </a>
        </div>

        <div class="mt-4">
            @include('livewire.dashboard.partials.tree-node', ['node' => $tree, 'depth' => 0])
        </div>
    </x-card>
</div>
