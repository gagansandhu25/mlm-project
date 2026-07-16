<div class="max-w-6xl mx-auto py-10 px-4 sm:px-6 lg:px-8 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Genealogy</h2>
            <p class="text-sm text-gray-500 mt-1">Click into a member to see their business info and drill into their downline.</p>
        </div>
        <a href="{{ route('network') }}" wire:navigate class="text-sm text-amber-600 hover:text-amber-700 font-medium whitespace-nowrap">
            View full tree &rarr;
        </a>
    </div>

    {{-- Breadcrumbs --}}
    <div class="flex flex-wrap items-center gap-1.5 text-sm">
        <button wire:click="focus({{ auth()->id() }})" type="button"
                class="px-3 py-1 rounded-full transition {{ empty($breadcrumbs) ? 'bg-amber-100 text-amber-800 font-semibold' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700' }}">
            You
        </button>
        @foreach ($breadcrumbs as $crumb)
            <span class="text-gray-300">/</span>
            @if ($loop->last)
                <span class="px-3 py-1 rounded-full bg-amber-100 text-amber-800 font-semibold">{{ $crumb['name'] }}</span>
            @else
                <button wire:click="focus({{ $crumb['id'] }})" type="button" class="px-3 py-1 rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition">
                    {{ $crumb['name'] }}
                </button>
            @endif
        @endforeach
    </div>

    {{-- Focus card --}}
    @php($u = $focus['user'])
    <x-card>
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center text-xl font-semibold shrink-0">
                {{ strtoupper(substr($u->name, 0, 1)) }}
            </div>
            <div class="min-w-0">
                <div class="text-base font-semibold text-gray-900 truncate">
                    {{ $u->name }}
                    @if ($u->id === auth()->id())
                        <span class="text-xs font-normal text-amber-600">(You)</span>
                    @endif
                </div>
                <div class="text-sm text-gray-500 truncate">
                    {{ ucfirst($u->status) }}
                    @if ($u->position)
                        &middot; {{ ucfirst($u->position) }} leg
                    @endif
                    &middot; Rank: {{ $u->rank?->name ?? 'Member' }}
                    &middot; Joined {{ $u->join_date?->format('M j, Y') ?? '—' }}
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mt-6 pt-6 border-t border-gray-100">
            <div>
                <div class="text-xs text-gray-500">Personal Volume</div>
                <div class="text-base font-semibold text-gray-900">${{ number_format((float) $u->sales_volume, 2) }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500">Team Volume</div>
                <div class="text-base font-semibold text-gray-900">${{ number_format($focus['teamVolume'], 2) }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500">Downline</div>
                <div class="text-base font-semibold text-gray-900">{{ number_format($focus['downlineCount']) }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500">Total Earnings</div>
                <div class="text-base font-semibold text-gray-900">${{ number_format((float) $u->total_earnings, 2) }}</div>
            </div>
            <div>
                <div class="text-xs text-gray-500">Left Leg</div>
                <div class="text-base font-semibold text-gray-900">{{ number_format($focus['leftLegCount']) }} <span class="text-xs font-normal text-gray-500">(${{ number_format($focus['leftLegVolume'], 2) }})</span></div>
            </div>
            <div>
                <div class="text-xs text-gray-500">Right Leg</div>
                <div class="text-base font-semibold text-gray-900">{{ number_format($focus['rightLegCount']) }} <span class="text-xs font-normal text-gray-500">(${{ number_format($focus['rightLegVolume'], 2) }})</span></div>
            </div>
        </div>
    </x-card>

    {{-- Direct downline --}}
    <div>
        <h3 class="text-sm font-medium text-gray-700 mb-3">
            {{ $u->id === auth()->id() ? 'Your direct downline' : "{$u->name}'s direct downline" }}
        </h3>

        @if (empty($children))
            <x-card class="text-sm text-gray-500 text-center">
                No downline under this member yet.
            </x-card>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($children as $child)
                    @php($cu = $child['user'])
                    <button type="button" wire:click="focus({{ $cu->id }})"
                            class="text-left bg-white rounded-xl border border-gray-200 shadow-sm p-4 hover:border-amber-300 hover:shadow-md transition">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center text-sm font-semibold shrink-0">
                                {{ strtoupper(substr($cu->name, 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-900 truncate">{{ $cu->name }}</div>
                                <div class="text-xs text-gray-500 truncate">
                                    {{ ucfirst($cu->status) }}
                                    @if ($cu->position)
                                        &middot; {{ ucfirst($cu->position) }} leg
                                    @endif
                                    &middot; {{ $cu->rank?->name ?? 'Member' }}
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 mt-3 pt-3 border-t border-gray-100 text-xs">
                            <div>
                                <span class="text-gray-500">Personal:</span>
                                <span class="font-medium text-gray-900">${{ number_format((float) $cu->sales_volume, 2) }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Team:</span>
                                <span class="font-medium text-gray-900">${{ number_format($child['teamVolume'], 2) }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Downline:</span>
                                <span class="font-medium text-gray-900">{{ number_format($child['downlineCount']) }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500">Legs:</span>
                                <span class="font-medium text-gray-900">{{ number_format($child['leftLegCount']) }} / {{ number_format($child['rightLegCount']) }}</span>
                            </div>
                        </div>
                    </button>
                @endforeach
            </div>
        @endif
    </div>
</div>
