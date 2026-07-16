<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('type', 10); // credit|debit
            $table->string('transaction_type', 20); // commission|withdrawal|purchase|bonus|adjustment
            $table->string('status', 20)->default('completed'); // pending|completed|failed|cancelled
            $table->unsignedBigInteger('reference_id')->nullable(); // e.g. commissions.id or withdrawal_requests.id
            $table->string('reference_type')->nullable();
            $table->text('description')->nullable();
            $table->decimal('balance_before', 15, 2);
            $table->decimal('balance_after', 15, 2);
            $table->timestamps();

            $table->index('user_id');
            $table->index('type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
