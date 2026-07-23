<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The "investment" is now just a completed, is_package Order — see
 * FixedYieldInvestmentService::runDaily(). This table's only remaining
 * consumer, fixed_yield_daily_accruals.investment_id, was already
 * repointed to orders.id by the migration immediately before this one,
 * so it's safe to drop here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('fixed_yield_investments');
    }

    public function down(): void
    {
        Schema::create('fixed_yield_investments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('invested_amount', 15, 2);
            $table->date('invested_at');
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index('status');
        });
    }
};
