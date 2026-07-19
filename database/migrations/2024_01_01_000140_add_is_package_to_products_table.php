<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_package')->default(false)->after('category');
        });

        // Backfill: every product in the current catalog is already a
        // joining package, tagged only by the free-text `category`
        // convention ('Packages'). Without this, package-tier
        // qualification conditions would silently evaluate against
        // zero for every existing product the moment that plan goes
        // live.
        DB::table('products')->where('category', 'Packages')->update(['is_package' => true]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_package');
        });
    }
};
