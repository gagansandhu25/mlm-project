<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_yield_daily_accruals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_id')->constrained('fixed_yield_investments')->cascadeOnDelete();
            $table->date('accrued_on'); // which calendar day this accrual is for
            $table->decimal('monthly_rate', 5, 2); // the rank's rate on the day this was calculated
            $table->decimal('base_amount', 15, 2); // invested_amount * (monthly_rate/30), before any cap truncation
            $table->decimal('amount', 15, 2); // final amount credited — may be less than base_amount if the 2x cap truncated it
            $table->string('status', 20)->default('pending'); // pending|paid|cancelled
            $table->dateTime('paid_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            // One accrual per investment per day — a hard DB-level guard
            // against double-paying if the scheduled command ever fires twice.
            $table->unique(['investment_id', 'accrued_on']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_yield_daily_accruals');
    }
};
