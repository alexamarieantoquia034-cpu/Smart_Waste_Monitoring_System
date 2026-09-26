@extends('layouts.app')

@section('content')

@php
    // Single source of truth for the browser runtime: model description,
    // label -> compartment map, camera configuration and save endpoint.
    $mlConfig = [
        'labels' => $model['labels'],
        'classes' => $model['classes'],
        'image_size' => $model['image_size'],
        'top_k' => $model['top_k'],
        'threshold' => $model['threshold'],
        'frame_interval_ms' => $model['frame_interval_ms'],
        'stable_frames' => $model['stable_frames'],
        'installed' => $model['installed'],
        'model_url' => $model['model_url'],
        'camera' => array_merge($camera, [
            // Lets the page say "this server can never reach that address"
            // rather than reporting a healthy-looking offline camera.
            'privateNetwork' => $cameraEndpoint['private_network'],
        ]),
        'endpoints' => [
            'stream' => route('api.esp32cam.stream'),
            'capture' => route('api.esp32cam.capture'),
            'status' => route('api.esp32cam.status'),
            'store' => route('api.classifications.store'),
        ],
    ];
@endphp

<div class="container-fluid" id="ml-live" data-config='@json($mlConfig)'>

    <div class="sw-page-head d-flex flex-wrap justify-content-between align-items-end gap-3">
        <div>
            <p class="sw-page-head__eyebrow">Machine Learning</p>
            <h1 class="sw-page-head__title">Live waste classification</h1>
            <p class="sw-page-head__lead">
                {{ $model['name'] }} &middot; {{ count($model['labels']) }} classes
                &middot; {{ $model['image_size'] }}&times;{{ $model['image_size'] }} input
                @if ($model['trained_at'])
                    &middot; trained
                    {{ \Illuminate\Support\Carbon::parse($model['trained_at'])->format('M d, Y') }}
                @endif
            </p>
        </div>

        <div class="d-flex align-items-center gap-2">
            {{-- Connection state of the Server-Sent Events feed; the text is
                 replaced in place by public/js/live-stream.js. --}}
            <span class="sw-pill sw-pill--muted" id="liveStatus" title="Real-time feed from the ESP32-CAM">
                <span class="sw-live-dot"></span> Connecting&hellip;
            </span>
            <span class="sw-pill sw-pill--muted" id="mlModelPill">
                <i class="bi bi-cpu"></i> <span id="mlModelPillText">Loading model&hellip;</span>
            </span>
            <a href="{{ route('classifications.index') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-clock-history me-1"></i> View logs
            </a>
        </div>
    </div>

    @unless ($model['installed'])
        <div class="sw-callout sw-callout--warn mb-4">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <div>
                <div class="sw-callout__title">Model files are not in place</div>
                <div class="sw-callout__text">
                    Missing {{ implode(', ', $model['missing_files']) }} in
                    <code>public/models/waste-classifier</code>. Run
                    <code>php artisan waste:sync-model</code> to copy the trained
                    model out of the <code>real dataset</code> folder.
                </div>
            </div>
        </div>
    @endunless

    @unless ($camera['configured'])
        <div class="sw-callout sw-callout--info mb-4">
            <i class="bi bi-info-circle-fill"></i>
            <div>
                <div class="sw-callout__title">ESP32-CAM is not configured yet</div>
                <div class="sw-callout__text">
                    Add <code>ESP32CAM_URL=http://&lt;camera-ip&gt;:81</code> to your
                    <code>.env</code> file to stream from the camera. Until then you
                    can still exercise the model with your webcam or an uploaded photo.
                </div>
            </div>
        </div>
    @endunless

    <div class="row g-4">

        {{-- ------------------------------------------------ Camera --}}
        <div class="col-xl-7">

            <div class="sw-card h-100">

                <div class="sw-card__head">
                    <div>
                        <h2 class="sw-card__title">
                            <i class="bi bi-camera-video"></i> Camera feed
                        </h2>
                        <p class="sw-card__sub" id="mlCameraMeta">
                            {{ $camera['url'] ?: 'No camera configured' }}
                        </p>
                    </div>
                    <span class="sw-pill sw-pill--muted" id="mlCameraPill">
                        <span class="sw-live-dot"></span> <span id="mlCameraPillText">Checking&hellip;</span>
                    </span>
                </div>

                <div class="sw-card__body">

                    {{-- Context for a camera address that only resolves on a LAN.
                         This is a note, not a failure: the app is often running
                         on that same network, in which case the feed works and
                         the status pill above says so. --}}
                    @if ($cameraEndpoint['message'])
                        <div class="sw-callout sw-callout--{{ $cameraEndpoint['private_network'] ? 'info' : 'warn' }} mb-3">
                            <i class="bi bi-{{ $cameraEndpoint['private_network'] ? 'router' : 'exclamation-triangle' }}"></i>
                            <div>
                                <div class="sw-callout__title">
                                    {{ $cameraEndpoint['private_network'] ? 'Local network address' : 'Camera not configured' }}
                                </div>
                                <div class="sw-callout__text">{{ $cameraEndpoint['message'] }}</div>
                            </div>
                        </div>
                    @endif

                    <div class="ml-stage" id="mlStage">
                        <img id="mlStream" alt="ESP32-CAM live stream" hidden>
                        <video id="mlWebcam" playsinline muted autoplay hidden></video>
                        <div class="ml-stage__empty" id="mlStageEmpty">
                            <i class="bi bi-camera-video-off"></i>
                            <p id="mlStageEmptyText">Press start to begin classifying.</p>
                        </div>
                        <span class="ml-stage__badge" id="mlStageBadge" hidden>Live</span>
                    </div>

                    <div class="ml-sources mt-3" role="group" aria-label="Frame source">
                        <button type="button" class="ml-source is-active" data-source="esp32">
                            <i class="bi bi-cpu"></i> ESP32-CAM
                        </button>
                        <button type="button" class="ml-source" data-source="webcam">
                            <i class="bi bi-laptop"></i> Webcam
                        </button>
                        <button type="button" class="ml-source" data-source="file">
                            <i class="bi bi-image"></i> Upload
                        </button>
                        <input type="file" id="mlFileInput" accept="image/*" hidden>
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                        <button type="button" class="btn btn-primary btn-sm" id="mlToggle">
                            <i class="bi bi-play-fill"></i> <span id="mlToggleText">Start classifying</span>
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="mlSave" disabled>
                            <i class="bi bi-save"></i> Save detection
                        </button>
                        <span class="sw-pill sw-pill--muted ms-auto" id="mlPerf">
                            <i class="bi bi-speedometer"></i> <span id="mlPerfText">idle</span>
                        </span>
                    </div>

                    <div class="sw-divider my-3"></div>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="ml-label" for="mlThreshold">
                                Auto-save confidence
                                <b id="mlThresholdValue">{{ (int) $model['threshold'] }}%</b>
                            </label>
                            <input type="range" class="ml-range" id="mlThreshold"
                                   min="10" max="99" step="1"
                                   value="{{ (int) $model['threshold'] }}">
                        </div>
                        <div class="col-sm-6 d-flex align-items-end">
                            <label class="ml-check">
                                <input type="checkbox" id="mlAutoSave" checked>
                                <span>
                                    Save automatically once the reading is stable
                                    ({{ $model['stable_frames'] }} frames)
                                </span>
                            </label>
                        </div>
                    </div>

                </div>

            </div>

        </div>

        {{-- --------------------------------------------- Prediction --}}
        <div class="col-xl-5">

            <div class="sw-card h-100">

                <div class="sw-card__head">
                    <div>
                        <h2 class="sw-card__title">
                            <i class="bi bi-bullseye"></i> Detection
                        </h2>
                        <p class="sw-card__sub">TensorFlow.js runs in your browser</p>
                    </div>
                </div>

                <div class="sw-card__body">

                    <div id="mlVerdict">
                        <div class="sw-empty mb-0">
                            <i class="bi bi-cpu"></i>
                            No detection yet.
                        </div>
                    </div>

                    <div class="mt-3">
                        <p class="ml-label mb-2">Class probabilities</p>
                        <div id="mlScores">
                            <p class="text-muted mb-0" style="font-size:.8rem">
                                Waiting for the first frame&hellip;
                            </p>
                        </div>
                    </div>

                    <p class="ml-note mt-3 mb-0" id="mlStatus" role="status" aria-live="polite"></p>

                </div>

            </div>

        </div>

    </div>


    {{-- ------------------------------------------- Model + history --}}
    <div class="row g-4 mt-0">

        <div class="col-lg-5">

            <div class="sw-card h-100">

                <div class="sw-card__head">
                    <div>
                        <h2 class="sw-card__title"><i class="bi bi-diagram-3"></i> Model</h2>
                        <p class="sw-card__sub">Classes the network can output</p>
                    </div>
                </div>

                <div class="sw-card__body">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            @foreach ($model['classes'] as $class)
                                <tr>
                                    <td>
                                        <span class="sw-chip" style="--chip:{{ $class['color'] }}">
                                            <i class="bi {{ $class['icon'] }}"></i>
                                            {{ $class['label'] }}
                                        </span>
                                    </td>
                                    <td class="text-end text-muted" style="font-size:.78rem">
                                        {{ $class['compartment_label'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            </div>

        </div>

        <div class="col-lg-7">

            <div class="sw-card h-100">

                <div class="sw-card__head">
                    <div>
                        <h2 class="sw-card__title">
                            <i class="bi bi-clock-history"></i> Recent detections
                        </h2>
                        <p class="sw-card__sub">Latest entries in the classification log</p>
                    </div>
                </div>

                <div class="sw-card__body">
                    <div id="mlRecent" data-fallback="{{ asset('images/barbie-pattern.jpg') }}">
                        @if ($logs->isNotEmpty())
                            @include('classifications.partials.recent', ['logs' => $logs])
                        @else
                            {{-- id="liveNoDetections" is what live-stream.js
                                 removes the first time a detection arrives. --}}
                            <div class="sw-empty" id="liveNoDetections">
                                <i class="bi bi-inbox"></i>
                                Nothing classified yet.
                            </div>
                        @endif
                    </div>
                </div>

            </div>

        </div>

    </div>

</div>

@endsection

@section('scripts')
    <script src="{{ $model['runtime_url'] }}"></script>
    <script src="{{ asset('js/live-classifier.js') }}"></script>
@endsection
