<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Waste Classification Model
    |--------------------------------------------------------------------------
    |
    | The project ships with a Teachable Machine (TensorFlow.js) model that was
    | trained on real waste photos. The canonical copy of the trained
    | artefacts lives in the "real dataset" folder at the project root; the
    | copy under public/ is the one the browser downloads at runtime.
    |
    | After every retrain refresh the served copy with:
    |
    |     php artisan waste:sync-model
    |
    */

    'model' => [
        'source' => base_path('real dataset'),
        'served' => public_path('models/waste-classifier'),

        // Public URLs (relative to the application root).
        'url' => 'models/waste-classifier',

        // TensorFlow.js runtime, pinned to the tfjsVersion in metadata.json
        // so the layer/weight manifest is parsed by a matching build.
        'runtime_url' => 'vendor/tfjs/tf.min.js',
        'runtime_version' => '1.7.4',
    ],

    /*
    |--------------------------------------------------------------------------
    | Class Labels -> Compartment Mapping
    |--------------------------------------------------------------------------
    |
    | The model only knows the class names it was trained on. This maps each
    | label onto one of the four physical compartments tracked in the
    | sensor_data table, which is what the decision support rules read.
    |
    | Keys are matched case-insensitively against the label.
    |
    */

    'label_map' => [
        'paper' => 'paper',
        'plastic' => 'plastic',

        // "Class 3" is the leftover Teachable Machine class name. It is routed
        // to the reject compartment until the model is retrained with a real
        // class name (e.g. "Biodegradable").
        'class 3' => 'reject',
    ],

    'default_compartment' => 'reject',

    'compartments' => [
        'plastic' => [
            'label' => 'Plastic bin',
            'icon' => 'bi-droplet-half',
            'color' => 'var(--sw-plastic)',
        ],
        'paper' => [
            'label' => 'Paper bin',
            'icon' => 'bi-file-earmark-text',
            'color' => 'var(--sw-paper)',
        ],
        'biodegradable' => [
            'label' => 'Biodegradable bin',
            'icon' => 'bi-leaf',
            'color' => 'var(--sw-bio)',
        ],
        'reject' => [
            'label' => 'Reject bin',
            'icon' => 'bi-trash3',
            'color' => 'var(--sw-reject)',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Inference Defaults
    |--------------------------------------------------------------------------
    |
    | image_size mirrors metadata.json; the browser reads it from the model
    | metadata so a retrain at a different resolution needs no code change.
    |
    | threshold is the confidence (in percent) an automatic save needs before
    | a detection is written to the classification log.
    |
    */

    'inference' => [
        'image_size' => 224,
        'top_k' => 3,
        'threshold' => (float) env('WASTE_CONFIDENCE_THRESHOLD', 70),
        'frame_interval_ms' => (int) env('WASTE_FRAME_INTERVAL_MS', 500),
        'stable_frames' => (int) env('WASTE_STABLE_FRAMES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Snapshot Storage
    |--------------------------------------------------------------------------
    |
    | Captured frames are written straight into public/ and stored as a
    | project-relative path so the existing views can render them with
    | asset($classification->image_path). No storage:link required.
    |
    | The GD extension is not required: frames arrive from the browser already
    | cropped and JPEG-encoded by a canvas.
    |
    */

    'snapshots' => [
        'directory' => 'images/classifications',
        'max_kb' => 512,
    ],

];
