<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawalServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_debits_the_wallet_immediately_and_creates_a_pending_withdrawal(): void
    {
        SystemSetting::set('minimum_payout_threshold', '20');
        SystemSetting::set('withdrawal_fee_percentage', '10');

        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 100, 'commission');

        $withdrawal = app(WithdrawalService::class)->request(
            $user,
            50,
            'bank_transfer',
            ['bank_name' => 'Test Bank', 'account_holder' => 'Test User', 'account_number' => '12345'],
        );

        $this->assertSame(WithdrawalRequest::STATUS_PENDING, $withdrawal->status);
        $this->assertEquals(5.00, $withdrawal->fee); // 10% of $50
        $this->assertEquals(50.00, $user->wallet->fresh()->balance); // 100 - 50 debited
    }

    public function test_request_below_minimum_threshold_is_rejected(): void
    {
        SystemSetting::set('minimum_payout_threshold', '20');

        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 100, 'commission');

        $this->expectException(\InvalidArgumentException::class);

        app(WithdrawalService::class)->request($user, 10, 'paypal', ['paypal_email' => 'a@b.com']);
    }

    public function test_request_exceeding_balance_is_rejected(): void
    {
        SystemSetting::set('minimum_payout_threshold', '0');

        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 30, 'commission');

        $this->expectException(\RuntimeException::class);

        app(WithdrawalService::class)->request($user, 50, 'paypal', ['paypal_email' => 'a@b.com']);
    }
}
