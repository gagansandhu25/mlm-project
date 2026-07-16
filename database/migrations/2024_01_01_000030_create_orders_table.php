<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('product_id')->references('id')->on('products')->restrictOnDelete();
            $table->string('order_number', 50)->unique();
            $table->decimal('amount', 15, 2);
            $table->decimal('commission_value', 15, 2);
            $table->string('status', 20)->default('pending'); // pending|completed|cancelled|refunded
            $table->dateTime('order_date');
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_status', 50)->default('pending');
            $table->boolean('commission_processed')->default(false);
            $table->timestamps();

            $table->index('user_id');
            $table->index('order_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
