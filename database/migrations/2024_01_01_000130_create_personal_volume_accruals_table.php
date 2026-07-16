<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_volume_accruals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('accrued_on'); // which calendar day this accrual is for
            $table->decimal('sales_volume_snapshot', 15, 2); // user's sales_volume at calculation time
            $table->decimal('percentage', 5, 2);
            $table->decimal('rank_multiplier', 4, 2)->default(1.00);
            $table->decimal('base_amount', 15, 2); // sales_volume_snapshot * percentage, before rank multiplier
            $table->decimal('amount', 15, 2); // final amount credited
            $table->string('status', 20)->default('pending'); // pending|paid|cancelled
            $table->dateTime('paid_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            // One accrual per user per day — a hard DB-level guard against
            // double-paying if the scheduled command ever fires twice.
            $table->unique(['user_id', 'accrued_on']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_volume_accruals');
    }
};
