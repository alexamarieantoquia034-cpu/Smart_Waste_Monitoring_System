<?php

namespace App\Support;

use App\Models\Alert;
use App\Models\SensorData;

/**
 * Turns raw bin levels into the decision-support alerts the dashboard shows.
 *
 * Shared by the device ingest endpoint (live ESP32-CAM readings) and the demo
 * seeder, so a bin that fills up is treated the same way whether the number
 * came off the hardware or out of a fixture.
 */
class FillLevelMonitor
{
    /**
     * sensor_data column => tracked compartment, in the order the UI lists them.
     *
     * @var array<string, string>
     */
    public const COMPARTMENTS = [
        'plastic_level' => 'plastic',
        'paper_level' => 'paper',
        'biodegradable_level' => 'biodegradable',
        'reject_level' => 'reject',
    ];

    /**
     * Percentage at or above which a compartment is considered full.
     */
    public function threshold(): float
    {
        return (float) config('esp32cam.fill_threshold', 85);
    }

    /**
     * Percentage at or above which a compartment is worth watching, even
     * before it is full.
     */
    public function warningThreshold(): float
    {
        return (float) config('esp32cam.fill_warn_threshold', 75);
    }

    /**
     * Current level of every compartment, keyed by compartment name.
     *
     * @return array<string, float>
     */
    public function levels(SensorData $reading): array
    {
        $levels = [];

        foreach (self::COMPARTMENTS as $column => $compartment) {
            $levels[$compartment] = (float) $reading->{$column};
        }

        return $levels;
    }

    /**
     * Compartments that are at or above the full threshold.
     *
     * @return array<string, float>
     */
    public function fullCompartments(SensorData $reading): array
    {
        return array_filter(
            $this->levels($reading),
            fn (float $level) => $level >= $this->threshold(),
        );
    }

    /**
     * Reconcile alerts with the current levels.
     *
     * Two tiers, matching the vocabulary the existing views already render:
     * "Full" recommends collecting immediately, "Near Full" recommends
     * scheduling a pickup. Open an alert when a bin first crosses the warning
     * line, escalate it if it keeps filling, and resolve it once the bin drops
     * back below that line.
     *
     * A reading is inserted on every telemetry push, so without the "already
     * open" check a full bin would raise a fresh alert every ten seconds and
     * bury the dashboard. Resolving matters just as much: without it a bin that
     * was emptied an hour ago would still show as critical forever, because
     * nothing else in the system clears the flag.
     *
     * @return array{opened: \Illuminate\Support\Collection<int, Alert>, resolved: \Illuminate\Support\Collection<int, Alert>}
     */
    public function evaluate(SensorData $reading): array
    {
        $threshold = $this->threshold();
        $warn = $this->warningThreshold();
        $opened = collect();
        $resolved = collect();

        foreach ($this->levels($reading) as $compartment => $level) {
            $open = Alert::query()
                ->where('compartment', $compartment)
                ->where('is_resolved', false)
                ->get();

            if ($level >= $warn) {
                $status = $level >= $threshold ? 'Full' : 'Near Full';

                if ($open->isEmpty()) {
                    $opened->push(Alert::create([
                        'sensor_data_id' => $reading->id,
                        'compartment' => $compartment,
                        'status' => $status,
                        'message' => sprintf(
                            '%s bin is at %.0f%% capacity. %s',
                            ucfirst($compartment),
                            $level,
                            $status === 'Full'
                                ? 'Collect immediately.'
                                : 'Schedule collection.',
                        ),
                    ]));

                    continue;
                }

                // Escalate an existing "Near Full" if the bin kept filling,
                // so the DSS recommendation changes with the reading.
                foreach ($open as $alert) {
                    if ($alert->status === $status) {
                        continue;
                    }

                    $alert->forceFill([
                        'status' => $status,
                        'message' => sprintf(
                            '%s bin is at %.0f%% capacity. %s',
                            ucfirst($compartment),
                            $level,
                            $status === 'Full'
                                ? 'Collect immediately.'
                                : 'Schedule collection.',
                        ),
                    ])->save();
                }

                continue;
            }

            foreach ($open as $alert) {
                $alert->forceFill([
                    'is_resolved' => true,
                    'resolved_at' => $reading->created_at,
                ])->save();

                $resolved->push($alert);
            }
        }

        return ['opened' => $opened, 'resolved' => $resolved];
    }
}
