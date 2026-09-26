<?php

namespace App\Console\Commands;

use App\Models\Alert;
use App\Models\ClassificationLog;
use App\Models\SensorData;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Wipes the collected readings, detections and alerts and regenerates the demo
 * history from empty.
 *
 * The demo data is a fixture, not real telemetry, so re-seeding needs a way to
 * start over once a demo has been running: the collection should read as
 * "bins started empty and have been filling since" rather than inheriting
 * whatever the previous run happened to leave behind. Deleting the rows also
 * restarts the id sequences, so the ids in the UI are contiguous again.
 *
 * Only the three telemetry tables are touched. Users, sessions and the model
 * files are left alone, so this cannot lock anyone out.
 */
class ResetDemoDataCommand extends Command
{
    protected $signature = 'waste:reset-demo
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Delete all sensor readings, detections and alerts, then re-seed the demo history from empty';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This deletes every reading, detection and alert. Continue?')) {
            $this->warn('Nothing was changed.');

            return self::SUCCESS;
        }

        $readings = SensorData::query()->count();
        $detections = ClassificationLog::query()->count();
        $alerts = Alert::query()->count();

        if ($readings + $detections + $alerts === 0) {
            $this->info('Nothing to clear.');

            return self::SUCCESS;
        }

        $this->components->warn(sprintf(
            'Removing %d readings, %d detections and %d alerts.',
            $readings,
            $detections,
            $alerts,
        ));

        // One statement and one transaction: TRUNCATE takes an ACCESS EXCLUSIVE
        // lock, so doing three DELETEs would drop the lock and re-take it
        // between each. RESTART IDENTITY is what actually makes the ids start
        // at 1 again, which DELETE alone does not do.
        DB::transaction(function () {
            DB::statement(
                'TRUNCATE TABLE alerts, classification_logs, sensor_data '
                .'RESTART IDENTITY CASCADE'
            );
        });

        $this->components->info('Tables cleared. Re-seeding from empty...');

        $this->call('db:seed', [
            '--class' => DemoDataSeeder::class,
            '--force' => true,
        ]);

        $this->newLine();
        $this->components->info(sprintf(
            'Done. %d readings, %d detections, %d open alerts.',
            SensorData::query()->count(),
            ClassificationLog::query()->count(),
            Alert::query()->where('is_resolved', false)->count(),
        ));

        return self::SUCCESS;
    }
}
