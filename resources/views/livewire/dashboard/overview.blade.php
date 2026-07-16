<div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8 space-y-6">
    {{-- Summary cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-stat-card label="Wallet Balance" value="${{ number_format($wallet?->balance ?? 0, 2) }}" :href="route('wallet')" linkText="Manage wallet">
            <x-slot:icon><x-app-icon name="wallet" /></x-slot:icon>
        </x-stat-card>

        <x-stat-card label="Total Earnings" value="${{ number_format(auth()->user()->total_earnings, 2) }}">
            <x-slot:icon><x-app-icon name="trending-up" /></x-slot:icon>
        </x-stat-card>

        <x-stat-card label="Total Downline" value="{{ $downlineCount }}" :href="route('network')" linkText="View network">
            <x-slot:icon><x-app-icon name="network" /></x-slot:icon>
        </x-stat-card>

        <x-stat-card label="Direct Sponsors" value="{{ $directSponsors }}">
            <x-slot:icon><x-app-icon name="user" /></x-slot:icon>
        </x-stat-card>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Rank progress --}}
        <x-card>
            <h3 class="text-base font-semibold text-gray-900">My Rank</h3>
            <p class="mt-1 text-sm text-gray-500">
                Current rank: <span class="font-semibold text-gray-900">{{ $rank?->name ?? 'Member' }}</span>
            </p>

            @if ($nextRank)
                <div class="mt-4 space-y-4">
                    <p class="text-sm text-gray-500">Progress toward <span class="font-semibold text-gray-700">{{ $nextRank->name }}</span></p>

                    <div>
                        <div class="flex justify-between text-xs text-gray-500 mb-1">
                            <span>Team volume</span>
                            <span>${{ number_format($teamVolume, 2) }} / ${{ number_format($nextRank->min_sales_volume, 2) }}</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="bg-amber-500 h-2 rounded-full transition-all" style="width: {{ $volumeProgress }}%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between text-xs text-gray-500 mb-1">
                            <span>Downline size</span>
                            <span>{{ $downlineCount }} / {{ $nextRank->min_downline }}</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="bg-amber-500 h-2 rounded-full transition-all" style="width: {{ $downlineProgress }}%"></div>
                        </div>
                    </div>
                </div>
            @else
                <p class="mt-4 text-sm text-gray-500">You've reached the highest available rank.</p>
            @endif
        </x-card>

        {{-- Referral link --}}
        <x-card x-data="{ copied: false }">
            <h3 class="text-base font-semibold text-gray-900">Referral Link</h3>
            <p class="mt-1 text-sm text-gray-500">Share this link to sponsor new members directly under you.</p>

            <div class="mt-4 flex items-center gap-2">
                <input type="text" readonly value="{{ $referralUrl }}" id="referral-url"
                       class="flex-1 rounded-lg border-gray-300 shadow-sm text-sm bg-gray-50" />
                <button type="button"
                        x-on:click="navigator.clipboard.writeText(document.getElementById('referral-url').value); copied = true; setTimeout(() => copied = false, 1500)"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50">
                    <span x-show="!copied">Copy</span>
                    <span x-show="copied" x-cloak class="text-amber-600">Copied!</span>
                </button>
            </div>

            <p class="mt-3 text-sm text-gray-500">Referral code: <span class="font-mono font-semibold text-gray-900">{{ auth()->user()->referral_code }}</span></p>
        </x-card>
    </div>

    {{-- Recent commissions --}}
    <x-card>
        <h3 class="text-base font-semibold text-gray-900">Recent Commissions</h3>

        @if ($recentCommissions->isEmpty())
            <p class="mt-4 text-sm text-gray-500">No commissions earned yet.</p>
        @else
            <table class="mt-4 min-w-full divide-y divide-gray-200">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Level</th>
                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($recentCommissions as $commission)
                        <tr>
                            <td class="px-3 py-2 text-sm text-gray-700">{{ $commission->calculated_at->format('M d, Y') }}</td>
                            <td class="px-3 py-2 text-sm text-gray-700 capitalize">{{ $commission->plan_type }}</td>
                            <td class="px-3 py-2 text-sm text-gray-700">{{ $commission->level }}</td>
                            <td class="px-3 py-2 text-sm text-right text-gray-900 font-medium">${{ number_format($commission->amount, 2) }}</td>
                            <td class="px-3 py-2 text-sm">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ $commission->status === 'paid' ? 'bg-green-100 text-green-800' : ($commission->status === 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">
                                    {{ ucfirst($commission->status) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>
</div>
