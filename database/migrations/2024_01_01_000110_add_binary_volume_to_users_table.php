<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Unmatched carry-forward volume per leg for the binary pairing bonus.
            $table->decimal('left_volume', 15, 2)->default(0)->after('total_earnings');
            $table->decimal('right_volume', 15, 2)->default(0)->after('left_volume');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['left_volume', 'right_volume']);
        });
    }
};
