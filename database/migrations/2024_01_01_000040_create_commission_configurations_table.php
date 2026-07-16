<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('plan_type', 20); // unilevel|binary|matrix
            $table->unsignedInteger('level');
            $table->decimal('percentage', 5, 2);
            $table->decimal('cap', 15, 2)->nullable(); // per-period cap for this level, null = uncapped
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable(); // plan-specific extras (e.g. matrix width, binary flushout)
            $table->timestamps();

            $table->unique(['plan_type', 'level']);
            $table->index('plan_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_configurations');
    }
};
