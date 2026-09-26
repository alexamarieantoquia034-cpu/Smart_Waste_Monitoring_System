<?php

namespace Database\Seeders;

use App\Models\ClassificationLog;
use App\Models\SensorData;
use App\Support\FillLevelMonitor;
use App\Support\WasteClassifier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fills an empty database with a believable day of bin levels, detections and
 * alerts so the dashboard, analytics and DSS pages have something to render
 * before the hardware is switched on.
 *
 * Idempotent: it does nothing once a sensor reading exists, so re-running the
 * seeder on every deploy (see railway.toml) never duplicates history.
 */
class DemoDataSeeder extends Seeder
{
    /** Readings to synthesise, oldest first. */
    protected const READINGS = 60;

    /** Detections to attach to the most recent readings. */
    protected const DETECTIONS = 18;

    public function run(WasteClassifier $classifier, FillLevelMonitor $monitor): void
    {
        if (SensorData::query()->exists()) {
            $this->command?->comment('Demo data already present, skipping.');

            return;
        }

        $this->command?->info('Seeding demo telemetry...');

        $labels = $classifier->labels();

        if ($labels === []) {
            $this->command?->warn('No trained model found, so no detections were seeded. Run php artisan waste:sync-model first.');

            return;
        }

        $readings = $this->seedReadings();
        $this->seedDetections($readings, $classifier, $labels);
        $this->seedAlerts($readings, $monitor);

        $this->command?->info(sprintf(
            'Seeded %d readings, %d detections and %d alerts.',
            $readings->count(),
            ClassificationLog::query()->count(),
            \App\Models\Alert::query()->count(),
        ));
    }

    /**
     * A rising fill curve per compartment, starting from empty.
     *
     * The bins begin at zero and climb over the seeded window, so the chart
     * reads as "collection started, waste is going in" rather than "these bins
     * were already full when the system started". That matters because nothing
     * is physically in the bins yet: until an ultrasonic sensor is wired, a
     * reading of 95% would be claiming a fullness that does not exist.
     *
     * Paper is the deliberate problem child and ends just past the "Near Full"
     * warning line, so the alert and DSS rules have something real to act on
     * while the other three stay comfortably under it.
     *
     * @return \Illuminate\Support\Collection<int, SensorData>
     */
    protected function seedReadings()
    {
        $profiles = [
            //             start  perStep  noise  ceiling
            'paper' => ['start' => 0.0, 'perStep' => 1.30, 'noise' => 1.6, 'ceiling' => 88.0],
            'plastic' => ['start' => 0.0, 'perStep' => 0.85, 'noise' => 1.4, 'ceiling' => 66.0],
            'biodegradable' => ['start' => 0.0, 'perStep' => 0.55, 'noise' => 1.2, 'ceiling' => 48.0],
            'reject' => ['start' => 0.0, 'perStep' => 0.30, 'noise' => 1.0, 'ceiling' => 32.0],
        ];

        $now = Carbon::now();
        $interval = (int) config('esp32cam.telemetry_interval', 10);
        $readings = collect();

        DB::transaction(function () use ($profiles, $now, $interval, &$readings) {
            for ($step = 0; $step < self::READINGS; $step++) {
                $levels = [];
                $distances = [];

                foreach ($profiles as $compartment => $profile) {
                    $value = $profile['start']
                        + ($profile['perStep'] * $step)
                        + $this->jitter($profile['noise']);

                    $level = max(0.0, min($profile['ceiling'], $value));

                    $levels[$compartment.'_level'] = round($level, 2);

                    // Ultrasonic sensors read distance to the top of the pile,
                    // so a fuller bin reports a smaller distance.
                    $distances[$compartment.'_distance'] = round(
                        max(2.0, 60.0 - ($level * 0.45)),
                        2,
                    );
                }

                $readings->push(SensorData::create([
                    ...$levels,
                    ...$distances,
                    'created_at' => $now->copy()->subSeconds($interval * (self::READINGS - $step)),
                    'updated_at' => $now->copy()->subSeconds($interval * (self::READINGS - $step)),
                ]));
            }
        });

        return $readings;
    }

    /**
     * Detections spread across the newest readings, cycling the model's own
     * labels so the compartment mapping stays whatever the model is trained
     * for. Confidence is drawn from a plausible band rather than uniform.
     *
     * $recent is the newest readings first, so index 0 is the most recent
     * reading. Cycling the labels in order therefore puts the first class,
     * Paper, on the newest row — the one the dashboard and the live page show
     * at the top.
     *
     * @param  \Illuminate\Support\Collection<int, SensorData>  $readings
     * @param  array<int, string>  $labels
     */
    protected function seedDetections($readings, WasteClassifier $classifier, array $labels): void
    {
        $recent = $readings->reverse()->take(self::DETECTIONS)->values();
        $count = count($labels);

        foreach ($recent as $index => $reading) {
            $label = $labels[$index % $count];
            $confidence = $this->jitter(9.0) + 78.0;

            ClassificationLog::create([
                'sensor_data_id' => $reading->id,
                'image_path' => null,
                'waste_type' => $label,
                'confidence' => round(min(99.0, $confidence), 2),
                'compartment' => $classifier->compartmentFor($label),
                'created_at' => $reading->created_at->copy()->addSeconds(30),
                'updated_at' => $reading->created_at->copy()->addSeconds(30),
            ]);
        }
    }

    /**
     * Run the same alert rule the live device uses, but only against the final
     * reading — older full bins were already emptied during the day.
     *
     * @param  \Illuminate\Support\Collection<int, SensorData>  $readings
     */
    protected function seedAlerts($readings, FillLevelMonitor $monitor): void
    {
        $latest = $readings->last();

        if (! $latest) {
            return;
        }

        $monitor->evaluate($latest);
    }
    /**
     * Deterministic-ish symmetric noise so repeated seeds look similar.
     */
    protected function jitter(float $spread): float
    {
        return (random_int(-1000, 1000) / 1000) * $spread;
    }
}
