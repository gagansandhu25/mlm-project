<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            // Daily personal-volume commissions aren't generated from a
            // single order — they're a periodic payout on accumulated
            // volume — so this can no longer be a required reference.
            $table->foreignId('order_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable(false)->change();
        });
    }
};
