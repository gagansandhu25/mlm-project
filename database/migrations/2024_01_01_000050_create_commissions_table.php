<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->references('id')->on('users')->cascadeOnDelete(); // earner
            $table->foreignId('from_user_id')->references('id')->on('users')->cascadeOnDelete(); // generator
            $table->foreignId('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->string('plan_type', 20); // unilevel|binary|matrix
            $table->decimal('base_amount', 15, 2); // amount before rank multiplier
            $table->decimal('amount', 15, 2); // final amount credited
            $table->decimal('percentage', 5, 2);
            $table->decimal('rank_multiplier', 4, 2)->default(1.00);
            $table->unsignedInteger('level')->default(1);
            $table->string('position', 10)->nullable();
            $table->string('status', 20)->default('pending'); // pending|paid|cancelled
            $table->text('description')->nullable();
            $table->dateTime('calculated_at');
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('plan_type');
            $table->index('status');
            $table->index(['user_id', 'calculated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
