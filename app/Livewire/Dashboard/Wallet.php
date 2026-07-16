<?php

namespace App\Livewire\Dashboard;

use App\Models\SystemSetting;
use App\Services\WithdrawalService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Wallet extends Component
{
    use WithPagination;

    public string $amount = '';

    public string $method = 'bank_transfer';

    public string $bank_name = '';

    public string $account_holder = '';

    public string $account_number = '';

    public string $paypal_email = '';

    public function requestWithdrawal(): void
    {
        $this->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:bank_transfer,paypal'],
            'bank_name' => ['required_if:method,bank_transfer', 'nullable', 'string', 'max:255'],
            'account_holder' => ['required_if:method,bank_transfer', 'nullable', 'string', 'max:255'],
            'account_number' => ['required_if:method,bank_transfer', 'nullable', 'string', 'max:50'],
            'paypal_email' => ['required_if:method,paypal', 'nullable', 'email'],
        ]);

        $accountDetails = $this->method === 'paypal'
            ? ['paypal_email' => $this->paypal_email]
            : [
                'bank_name' => $this->bank_name,
                'account_holder' => $this->account_holder,
                'account_number' => $this->account_number,
            ];

        try {
            app(WithdrawalService::class)->request(
                auth()->user(),
                (float) $this->amount,
                $this->method,
                $accountDetails,
            );

            $this->reset(['amount', 'bank_name', 'account_holder', 'account_number', 'paypal_email']);
            session()->flash('status', 'Withdrawal request submitted and is pending admin review.');
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->addError('amount', $e->getMessage());
        }
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.dashboard.wallet', [
            'wallet' => $user->wallet,
            'transactions' => $user->walletTransactions()->latest()->paginate(10),
            'withdrawals' => $user->withdrawalRequests()->latest()->limit(10)->get(),
            'minimumPayout' => (float) SystemSetting::get('minimum_payout_threshold', 50),
            'feePercentage' => (float) SystemSetting::get('withdrawal_fee_percentage', 0),
        ]);
    }
}
