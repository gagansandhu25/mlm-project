<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('user')->after('password'); // super_admin|admin|user
            $table->string('phone', 20)->nullable()->after('role');
            $table->string('avatar')->nullable()->after('phone');

            $table->foreignId('parent_id')->nullable()->after('avatar')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreignId('sponsor_id')->nullable()->after('parent_id')
                ->references('id')->on('users')->nullOnDelete();

            $table->string('path')->nullable()->after('sponsor_id'); // e.g. '1/2/5/12'
            $table->unsignedInteger('depth')->default(0)->after('path');
            $table->string('position', 10)->nullable()->after('depth'); // left|right (binary), 1..n (matrix)

            $table->string('referral_code', 50)->unique()->nullable()->after('position');
            $table->string('status', 20)->default('active')->after('referral_code'); // active|inactive|suspended

            $table->foreignId('rank_id')->nullable()->after('status')
                ->references('id')->on('ranks')->nullOnDelete();

            $table->dateTime('join_date')->nullable()->after('rank_id');
            $table->dateTime('last_active')->nullable()->after('join_date');

            $table->decimal('sales_volume', 15, 2)->default(0)->after('last_active');
            $table->decimal('total_earnings', 15, 2)->default(0)->after('sales_volume');

            $table->index('path');
            $table->index('depth');
            $table->index('position');
            $table->index('parent_id');
            $table->index('sponsor_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['sponsor_id']);
            $table->dropForeign(['rank_id']);
            $table->dropColumn([
                'role', 'phone', 'avatar', 'parent_id', 'sponsor_id', 'path', 'depth',
                'position', 'referral_code', 'status', 'rank_id', 'join_date',
                'last_active', 'sales_volume', 'total_earnings',
            ]);
        });
    }
};
