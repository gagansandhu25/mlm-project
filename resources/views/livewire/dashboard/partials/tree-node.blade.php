@php($u = $node['user'])
<div>
    <div class="flex items-center gap-2 py-1.5" style="padding-left: {{ $depth * 28 }}px">
        @if ($node['hasChildren'])
            <button wire:click="toggle({{ $u->id }})" type="button"
                    class="shrink-0 w-6 h-6 flex items-center justify-center rounded-md border border-gray-300 text-xs font-bold text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                {{ $node['expanded'] ? '−' : '+' }}
            </button>
        @else
            <span class="shrink-0 w-6 h-6"></span>
        @endif

        <div class="flex items-center gap-3 bg-gray-50 rounded-lg px-3 py-1.5 flex-1">
            <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center text-sm font-semibold shrink-0">
                {{ strtoupper(substr($u->name, 0, 1)) }}
            </div>
            <div class="min-w-0">
                <div class="text-sm font-medium text-gray-900 truncate">
                    {{ $u->name }}
                    @if ($u->id === auth()->id())
                        <span class="text-xs text-amber-600 font-normal">(You)</span>
                    @endif
                </div>
                <div class="text-xs text-gray-500 truncate">
                    {{ ucfirst($u->status) }}
                    @if ($u->position)
                        &middot; {{ ucfirst($u->position) }} leg
                    @endif
                    &middot; Rank: {{ $u->rank?->name ?? 'Member' }}
                </div>
            </div>
        </div>
    </div>

    @if ($node['expanded'])
        @foreach ($node['children'] as $child)
            @include('livewire.dashboard.partials.tree-node', ['node' => $child, 'depth' => $depth + 1])
        @endforeach
    @endif
</div>
