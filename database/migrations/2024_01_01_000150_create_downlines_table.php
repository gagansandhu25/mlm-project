<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('downlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ancestor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('descendant_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('depth'); // 0 = self-row
            $table->timestamps();

            $table->unique(['ancestor_id', 'descendant_id']);
            $table->index('descendant_id'); // unique() above already covers ancestor_id-first lookups
        });

        $this->backfillFromExistingPaths();
    }

    public function down(): void
    {
        Schema::dropIfExists('downlines');
    }

    /**
     * Backfill closure rows from the still-present `path` column for
     * whichever users already exist. No-op on a fresh install/test DB
     * (no users yet); does the real backfill on an existing DB. Guarded
     * against accidental re-runs, and the DML is transactional even
     * though the preceding CREATE TABLE is not.
     */
    private function backfillFromExistingPaths(): void
    {
        if (DB::table('downlines')->exists()) {
            return;
        }

        DB::transaction(function () {
            User::query()->whereNotNull('path')->orderBy('id')->chunk(200, function ($users) {
                foreach ($users as $user) {
                    // Reuses TreeService::ancestorIdsClosestFirst()'s exact
                    // logic — TreeService will no longer have it by the
                    // time this file is read historically.
                    $ids = array_map('intval', explode('/', $user->path));
                    $count = count($ids);

                    $rows = [];
                    foreach ($ids as $i => $ancestorId) {
                        $rows[] = [
                            'ancestor_id' => $ancestorId,
                            'descendant_id' => $user->id,
                            'depth' => $count - 1 - $i,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    DB::table('downlines')->insert($rows);
                }
            });
        });
    }
};
