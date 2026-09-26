<?php

use Database\Seeders\DemoDataSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Regenerate the demo history so the collection starts from empty.
 *
 * The seeded fixtures used to begin part-way up the scale (bins already at
 * 12-22% on the first reading), which read as though waste had been sitting in
 * the bins before the system started. Nothing is physically in them until a
 * fill sensor is wired, so the first reading should be zero and the rest should
 * climb from there.
 *
 * This exists as a migration rather than a one-off manual reset because the
 * production database is only reachable from inside Railway's network, so
 * "migrate --force" on deploy is the one hook that is guaranteed to run
 * everywhere the app is installed.
 *
 * It is deliberately narrow: it only fires when the existing readings look
 * like the old fixture (they do not start at zero). Real telemetry gathered
 * from a wired sensor, and an already-correct dataset, are both left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sensor_data')) {
            return;
        }

        $oldest = DB::table('sensor_data')->orderBy('id')->first();

        if ($oldest === null) {
            return;
        }

        $startsEmpty = collect(['plastic_level', 'paper_level', 'biodegradable_level', 'reject_level'])
            ->every(fn (string $column) => (float) $oldest->{$column} <= 5.0);

        if ($startsEmpty) {
            return;
        }

        DB::statement('TRUNCATE TABLE alerts, classification_logs, sensor_data RESTART IDENTITY CASCADE');

        // Migration has no call() helper, so go through Artisan directly.
        Artisan::call('db:seed', [
            '--class' => DemoDataSeeder::class,
            '--force' => true,
        ]);
    }

    public function down(): void
    {
        // Regenerating demo data is not reversible, and the seed is
        // deterministic enough to re-run at any time with waste:reset-demo.
    }
};
