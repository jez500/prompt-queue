<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->unsignedInteger('position')->default(0)->after('color');

            $table->index(['user_id', 'position']);
        });

        $this->backfillPositions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'position']);
            $table->dropColumn('position');
        });
    }

    /**
     * Seed each user's projects in the name order the sidebar used until now, so
     * nothing appears to move the first time this runs.
     */
    private function backfillPositions(): void
    {
        DB::table('projects')
            ->orderBy('user_id')
            ->orderBy('name')
            ->get(['id', 'user_id'])
            ->groupBy('user_id')
            ->each(function ($projects): void {
                $projects->values()->each(function (object $project, int $index): void {
                    DB::table('projects')
                        ->where('id', $project->id)
                        ->update(['position' => $index]);
                });
            });
    }
};
