<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\ClassificationLog;
use App\Models\SensorData;
use App\Support\FillLevelMonitor;
use App\Support\Jpeg;
use App\Support\WasteClassifier;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ingest endpoint the ESP32-CAM pushes into.
 *
 * The camera reports on its own schedule, so the dashboard keeps updating
 * with no browser open on the live classification page. Every route here is
 * protected by App\Http\Middleware\AuthenticateDevice.
 */
class DeviceController extends Controller
{
    /**
     * Columns a device may report, mapped to their validation rule.
     *
     * The firmware wires up a single ultrasonic sensor by default, so most
     * payloads carry one compartment. Whatever a payload omits is carried
     * forward from the previous reading instead of being written as zero —
     * otherwise one bin reporting in would silently empty the other three.
     *
     * @var array<string, string>
     */
    protected const LEVEL_COLUMNS = [
        'plastic_level' => 'numeric|min:0|max:100',
        'paper_level' => 'numeric|min:0|max:100',
        'biodegradable_level' => 'numeric|min:0|max:100',
        'reject_level' => 'numeric|min:0|max:100',
    ];

    /** @var array<string, string> */
    protected const DISTANCE_COLUMNS = [
        'plastic_distance' => 'numeric|min:0|max:500',
        'paper_distance' => 'numeric|min:0|max:500',
        'biodegradable_distance' => 'numeric|min:0|max:500',
        'reject_distance' => 'numeric|min:0|max:500',
    ];

    /**
     * Heartbeat. Lets the firmware confirm the key matches before it starts
     * sending real readings, and tells it how often to report.
     */
    public function ping(Request $request, FillLevelMonitor $monitor)
    {
        $latest = SensorData::query()->latest('id')->first();

        return response()->json([
            'ok' => true,
            'server_time' => Carbon::now()->toIso8601String(),
            'telemetry_interval' => (int) config('esp32cam.telemetry_interval', 10),
            'fill_threshold' => $monitor->threshold(),
            'compartments' => array_keys(FillLevelMonitor::COMPARTMENTS),
            'latest_reading' => $latest ? $this->readingPayload($latest, $monitor) : null,
        ]);
    }

    /**
     * Store one bin-level reading and open an alert for anything full.
     */
    public function telemetry(Request $request, FillLevelMonitor $monitor)
    {
        $data = $request->validate([
            'device_id' => ['nullable', 'string', 'max:64'],
            'firmware' => ['nullable', 'string', 'max:32'],
            'ip' => ['nullable', 'string', 'max:45'],
            'uptime_ms' => ['nullable', 'integer', 'min:0'],
            'rssi' => ['nullable', 'integer', 'between:-120,0'],
            ...self::LEVEL_COLUMNS,
            ...self::DISTANCE_COLUMNS,
        ]);

        $previous = SensorData::query()->latest('id')->first();
        $attributes = [];

        foreach ([...self::LEVEL_COLUMNS, ...self::DISTANCE_COLUMNS] as $column => $_rule) {
            if (array_key_exists($column, $data) && $data[$column] !== null) {
                $attributes[$column] = round((float) $data[$column], 2);
            } elseif ($previous) {
                $attributes[$column] = (float) $previous->{$column};
            }
            // A column missing from both the payload and history keeps its
            // database default of 0.
        }

        $reading = SensorData::create($attributes);
        $alerts = $monitor->evaluate($reading);

        Log::info('ESP32-CAM telemetry received.', [
            'device_id' => $data['device_id'] ?? 'unknown',
            'rssi' => $data['rssi'] ?? null,
            'alerts_opened' => $alerts['opened']->count(),
            'alerts_resolved' => $alerts['resolved']->count(),
        ]);

        return response()->json([
            'ok' => true,
            'reading' => $this->readingPayload($reading, $monitor),
            'alerts' => [
                'opened' => $alerts['opened']->map(fn (Alert $alert) => $this->alertPayload($alert))->all(),
                'resolved' => $alerts['resolved']->map(fn (Alert $alert) => $this->alertPayload($alert))->all(),
            ],
        ], 201);
    }

    /**
     * Record a classification produced by the device itself.
     *
     * The bundled firmware only streams, so this is for a future on-device
     * model. The label is checked against the trained classes and the
     * compartment is derived server-side, exactly as for a browser
     * detection, so a buggy or compromised device cannot mislabel a row.
     */
    public function detection(Request $request, WasteClassifier $classifier)
    {
        $maxImageBytes = (int) config('waste.snapshots.max_kb') * 1024;

        $data = $request->validate([
            'label' => ['required', 'string', 'max:191'],
            'confidence' => ['required', 'numeric', 'min:0', 'max:100'],
            'device_id' => ['nullable', 'string', 'max:64'],
            // base64 inflates payloads by roughly 4/3
            'image' => ['nullable', 'string', 'max:'.(int) ceil($maxImageBytes * 1.4)],
        ]);

        if (! $classifier->isKnownLabel($data['label'])) {
            throw ValidationException::withMessages([
                'label' => 'Unknown class label. This model only knows: '
                    .implode(', ', $classifier->labels()).'.',
            ]);
        }

        $compartment = $classifier->compartmentFor($data['label']);

        $log = ClassificationLog::create([
            'sensor_data_id' => $this->currentSensorReading(),
            'image_path' => $this->storeSnapshot($data['image'] ?? null),
            'waste_type' => $data['label'],
            'confidence' => round((float) $data['confidence'], 2),
            'compartment' => $compartment,
        ]);

        return response()->json([
            'ok' => true,
            'classification' => [
                'id' => $log->id,
                'waste_type' => $log->waste_type,
                'confidence' => (float) $log->confidence,
                'compartment' => $log->compartment,
                'compartment_label' => $classifier->compartmentMeta($compartment)['label'],
                'image_url' => $log->image_path ? asset($log->image_path) : null,
                'created_at' => $log->created_at->toDateTimeString(),
            ],
        ], 201);
    }

    /**
     * Shape a reading for the device response and the live event stream.
     *
     * @return array<string, mixed>
     */
    protected function readingPayload(SensorData $reading, FillLevelMonitor $monitor): array
    {
        return [
            'id' => $reading->id,
            'levels' => $monitor->levels($reading),
            'distances' => [
                'plastic' => (float) $reading->plastic_distance,
                'paper' => (float) $reading->paper_distance,
                'biodegradable' => (float) $reading->biodegradable_distance,
                'reject' => (float) $reading->reject_distance,
            ],
            'full' => array_keys($monitor->fullCompartments($reading)),
            'created_at' => $reading->created_at->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function alertPayload(Alert $alert): array
    {
        return [
            'id' => $alert->id,
            'compartment' => $alert->compartment,
            'status' => $alert->status,
            'message' => $alert->message,
            'created_at' => $alert->created_at->toDateTimeString(),
        ];
    }

    /**
     * classification_logs.sensor_data_id is non-nullable, so a detection
     * always belongs to a reading. Attach it to the newest one, creating a
     * zeroed reading if the device has not reported yet.
     */
    protected function currentSensorReading(): int
    {
        $reading = SensorData::query()->latest('id')->first();

        if (! $reading) {
            $reading = SensorData::create([]);
        }

        return (int) $reading->id;
    }

    /**
     * Persist a device capture under public/images/classifications.
     *
     * Same path and same JPEG validation the browser route uses, so a
     * snapshot looks identical whichever side produced it.
     */
    protected function storeSnapshot(?string $dataUrl): ?string
    {
        if (blank($dataUrl)) {
            return null;
        }

        if (! preg_match('#^data:image/jpe?g;base64,(.+)$#i', $dataUrl, $matches)) {
            throw ValidationException::withMessages([
                'image' => 'The snapshot must be a base64 encoded JPEG data URL.',
            ]);
        }

        $binary = base64_decode($matches[1], true);

        if ($binary === false || ! Jpeg::isJpeg($binary)) {
            throw ValidationException::withMessages([
                'image' => 'The snapshot is not a valid JPEG image.',
            ]);
        }

        $maxBytes = (int) config('waste.snapshots.max_kb') * 1024;

        if (strlen($binary) > $maxBytes) {
            throw ValidationException::withMessages([
                'image' => 'The snapshot exceeds the '.config('waste.snapshots.max_kb').' KB limit.',
            ]);
        }

        $relative = config('waste.snapshots.directory')
            .'/'.now()->format('Y/m')
            .'/'.Str::uuid()->toString().'.jpg';

        $absolute = public_path($relative);

        File::ensureDirectoryExists(dirname($absolute));
        File::put($absolute, $binary);

        return $relative;
    }
}
