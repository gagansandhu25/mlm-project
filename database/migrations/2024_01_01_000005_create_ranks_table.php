<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedInteger('level')->unique(); // ordering: 1 = lowest rank
            $table->string('icon')->nullable();
            $table->decimal('min_sales_volume', 15, 2)->default(0);
            $table->unsignedInteger('min_downline')->default(0);
            $table->decimal('commission_multiplier', 4, 2)->default(1.00);
            $table->decimal('rank_commission_rate', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranks');
    }
};
