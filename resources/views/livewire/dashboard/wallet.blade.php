<div class="max-w-5xl mx-auto py-10 px-4 sm:px-6 lg:px-8 space-y-6">
    @if (session('status'))
        <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Balance + withdraw form --}}
        <div class="lg:col-span-1 space-y-6">
            <x-stat-card label="Available Balance" value="${{ number_format($wallet?->balance ?? 0, 2) }}">
                <x-slot:icon><x-app-icon name="wallet" /></x-slot:icon>
            </x-stat-card>
            <p class="-mt-4 text-xs text-gray-500 px-1">
                Minimum withdrawal: ${{ number_format($minimumPayout, 2) }} &middot; Fee: {{ rtrim(rtrim(number_format($feePercentage, 2), '0'), '.') }}%
            </p>

            <x-card>
                <h3 class="text-base font-semibold text-gray-900 mb-4">Request Withdrawal</h3>

                <form wire:submit="requestWithdrawal" class="space-y-4">
                    <div>
                        <x-input-label for="amount" value="Amount (USD)" />
                        <x-text-input wire:model="amount" id="amount" type="number" step="0.01" min="0.01" class="block mt-1 w-full" />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="method" value="Payout Method" />
                        <select wire:model.live="method" id="method" class="block mt-1 w-full rounded-lg border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="paypal">PayPal</option>
                        </select>
                    </div>

                    @if ($method === 'bank_transfer')
                        <div>
                            <x-input-label for="bank_name" value="Bank Name" />
                            <x-text-input wire:model="bank_name" id="bank_name" type="text" class="block mt-1 w-full" />
                            <x-input-error :messages="$errors->get('bank_name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="account_holder" value="Account Holder" />
                            <x-text-input wire:model="account_holder" id="account_holder" type="text" class="block mt-1 w-full" />
                            <x-input-error :messages="$errors->get('account_holder')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="account_number" value="Account Number" />
                            <x-text-input wire:model="account_number" id="account_number" type="text" class="block mt-1 w-full" />
                            <x-input-error :messages="$errors->get('account_number')" class="mt-2" />
                        </div>
                    @else
                        <div>
                            <x-input-label for="paypal_email" value="PayPal Email" />
                            <x-text-input wire:model="paypal_email" id="paypal_email" type="email" class="block mt-1 w-full" />
                            <x-input-error :messages="$errors->get('paypal_email')" class="mt-2" />
                        </div>
                    @endif

                    <x-primary-button>Submit Request</x-primary-button>
                </form>
            </x-card>

            @if ($withdrawals->isNotEmpty())
                <x-card>
                    <h3 class="text-base font-semibold text-gray-900 mb-4">My Withdrawal Requests</h3>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($withdrawals as $withdrawal)
                            <li class="py-2 flex items-center justify-between text-sm">
                                <div>
                                    <div class="font-medium text-gray-900">${{ number_format($withdrawal->amount, 2) }}</div>
                                    <div class="text-xs text-gray-500">{{ $withdrawal->created_at->format('M d, Y') }} &middot; {{ str_replace('_', ' ', $withdrawal->method) }}</div>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                    {{ match($withdrawal->status) {
                                        'completed' => 'bg-green-100 text-green-800',
                                        'approved' => 'bg-blue-100 text-blue-800',
                                        'rejected' => 'bg-red-100 text-red-800',
                                        default => 'bg-yellow-100 text-yellow-800',
                                    } }}">
                                    {{ ucfirst($withdrawal->status) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>

        {{-- Transaction history --}}
        <div class="lg:col-span-2">
            <x-card>
                <h3 class="text-base font-semibold text-gray-900 mb-4">Transaction History</h3>

                @if ($transactions->isEmpty())
                    <p class="text-sm text-gray-500">No wallet transactions yet.</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                <th class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($transactions as $transaction)
                                <tr>
                                    <td class="px-3 py-2 text-sm text-gray-700">{{ $transaction->created_at->format('M d, Y') }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-700 capitalize">{{ str_replace('_', ' ', $transaction->transaction_type) }}</td>
                                    <td class="px-3 py-2 text-sm text-gray-500">{{ $transaction->description }}</td>
                                    <td class="px-3 py-2 text-sm text-right font-medium {{ $transaction->type === 'credit' ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $transaction->type === 'credit' ? '+' : '-' }}${{ number_format($transaction->amount, 2) }}
                                    </td>
                                    <td class="px-3 py-2 text-sm text-right text-gray-900">${{ number_format($transaction->balance_after, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="mt-4">
                        {{ $transactions->links() }}
                    </div>
                @endif
            </x-card>
        </div>
    </div>
</div>
