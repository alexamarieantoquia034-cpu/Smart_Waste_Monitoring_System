<?php

namespace App\Http\Controllers;

use App\Models\ClassificationLog;
use App\Models\SensorData;
use App\Support\Jpeg;
use App\Support\WasteClassifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClassificationController extends Controller
{
    public function index()
    {
        $classifications = ClassificationLog::latest()->paginate(10);

        return view('classifications.index', compact('classifications'));
    }

    public function show(ClassificationLog $classification)
    {
        return view('classifications.show', compact('classification'));
    }

    /**
     * Live machine-learning classification driven by the ESP32-CAM stream.
     *
     * Inference runs in the browser through TensorFlow.js, so this action
     * only supplies the model description and the camera configuration.
     */
    public function live(WasteClassifier $classifier)
    {
        return view('classifications.live', [
            'model' => $classifier->summary(),
            'camera' => [
                'enabled' => (bool) config('esp32cam.enabled'),
                'configured' => config('esp32cam.base_url') !== '',
                'url' => config('esp32cam.base_url'),
                'framesize' => config('esp32cam.framesize'),
            ],
            'logs' => ClassificationLog::latest()->limit(6)->get(),
        ]);
    }

    /**
     * Model description consumed by the browser runtime.
     */
    public function model(WasteClassifier $classifier)
    {
        return response()->json($classifier->summary());
    }

    /**
     * Persist a detection that was classified in the browser.
     *
     * The label is validated against the trained classes and the compartment
     * is resolved server-side, so a crafted request cannot mislabel a log
     * entry or point it at the wrong bin.
     */
    public function store(Request $request, WasteClassifier $classifier)
    {
        $maxImageBytes = (int) config('waste.snapshots.max_kb') * 1024;

        $data = $request->validate([
            'label' => ['required', 'string', 'max:191'],
            'confidence' => ['required', 'numeric', 'min:0', 'max:100'],
            'source' => ['nullable', 'string', 'max:32'],
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

        $classification = ClassificationLog::create([
            'sensor_data_id' => $this->currentSensorReading(),
            'image_path' => $this->storeSnapshot($data['image'] ?? null),
            'waste_type' => $data['label'],
            'confidence' => round((float) $data['confidence'], 2),
            'compartment' => $compartment,
        ]);

        return response()->json([
            'saved' => true,
            'classification' => [
                'id' => $classification->id,
                'waste_type' => $classification->waste_type,
                'confidence' => (float) $classification->confidence,
                'compartment' => $classification->compartment,
                'compartment_label' => $classifier->compartmentMeta($compartment)['label'],
                'image_url' => $classification->image_path
                    ? asset($classification->image_path)
                    : null,
                'created_at' => $classification->created_at->toDateTimeString(),
            ],
        ], 201);
    }

    /**
     * classification_logs.sensor_data_id is a non-nullable foreign key, so a
     * detection always belongs to a reading. Attach it to the most recent
     * one, creating a zeroed reading when the device has not reported yet.
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
     * Persist a canvas capture under public/images/classifications.
     *
     * The returned path is project-relative so the existing views can render
     * it with asset($classification->image_path). The browser already sends a
     * cropped, canvas-encoded JPEG, so no server-side resizing (and no GD
     * extension) is required.
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
                'image' => 'The snapshot exceeds the '
                    .config('waste.snapshots.max_kb').' KB limit.',
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
