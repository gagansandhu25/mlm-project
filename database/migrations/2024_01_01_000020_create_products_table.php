<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 15, 2);
            $table->decimal('commission_value', 15, 2); // base amount eligible for commission (may differ from price)
            $table->string('image')->nullable();
            $table->integer('stock')->default(0);
            $table->string('category', 100)->nullable();
            $table->string('status', 20)->default('active'); // active|inactive
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
