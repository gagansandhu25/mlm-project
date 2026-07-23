<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_yield_daily_accruals', function (Blueprint $table) {
            // Foreign key first: MySQL uses this composite unique index to
            // satisfy the FK's index requirement on investment_id, so
            // dropping the index before the FK fails with "needed in a
            // foreign key constraint".
            $table->dropForeign(['investment_id']);
            $table->dropUnique(['investment_id', 'accrued_on']);
            $table->dropColumn('investment_id');
        });

        Schema::table('fixed_yield_daily_accruals', function (Blueprint $table) {
            $table->foreignId('order_id')->after('id')->constrained('orders')->cascadeOnDelete();
            $table->unique(['order_id', 'accrued_on']);
        });
    }

    public function down(): void
    {
        Schema::table('fixed_yield_daily_accruals', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropUnique(['order_id', 'accrued_on']);
            $table->dropColumn('order_id');
        });

        Schema::table('fixed_yield_daily_accruals', function (Blueprint $table) {
            $table->foreignId('investment_id')->after('id')->constrained('fixed_yield_investments')->cascadeOnDelete();
            $table->unique(['investment_id', 'accrued_on']);
        });
    }
};
