<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ESP32-CAM Bridge
    |--------------------------------------------------------------------------
    |
    | The camera (an ESP32-CAM running esp32cam/esp32cam_ml_camera.ino) serves
    | an MJPEG stream on its own web server. Laravel proxies that stream so the
    | browser receives it same-origin, which is what keeps the capture canvas
    | untainted and lets tf.browser.fromPixels() read the pixels.
    |
    | Fill in ESP32CAM_URL with the camera's LAN address, e.g.
    | http://192.168.1.50:81
    |
    */

    'enabled' => (bool) env('ESP32CAM_ENABLED', true),

    'base_url' => rtrim((string) env('ESP32CAM_URL', ''), '/'),

    /*
    | Sent as ?api_key=... when set. Leave empty if the sketch does not use
    | one; the bundled sketch serves an open LAN stream.
    |
    */

    'api_key' => env('ESP32CAM_API_KEY'),

    'stream_path' => env('ESP32CAM_STREAM_PATH', '/stream'),
    'capture_path' => env('ESP32CAM_CAPTURE_PATH', '/capture'),

    /*
    | Frame size requested from the camera when grabbing a single still. The
    | model resizes to 224x224 anyway, but a larger source frame keeps more
    | detail in the centre crop. Must match a FRAMESIZE constant in the
    | ESP32-CAM camera_pins.h.
    |
    */

    'framesize' => env('ESP32CAM_FRAMESIZE', 'VGA'),

    /*
    |--------------------------------------------------------------------------
    | Timeouts (seconds)
    |--------------------------------------------------------------------------
    |
    | The stream is an endless multipart response, so its read timeout stays
    | at 0 (never time out) and the request is closed by the browser instead.
    |
    */

    'connect_timeout' => (float) env('ESP32CAM_CONNECT_TIMEOUT', 3),
    'read_timeout' => (float) env('ESP32CAM_READ_TIMEOUT', 8),
    'stream_read_timeout' => (float) env('ESP32CAM_STREAM_TIMEOUT', 0),

    /*
    |--------------------------------------------------------------------------
    | Device ingest
    |--------------------------------------------------------------------------
    |
    | The camera also pushes data *into* the application: it reports bin
    | levels and any on-device detection on its own schedule, so the
    | dashboard keeps updating without a browser open on the live page.
    |
    | Requests to /api/device/* must carry this key in the X-Device-Key
    | header. It has to match the key compiled into the firmware
    | (esp32cam/include/secrets.h) — leaving it empty disables ingest
    | entirely, which is the right default for a public deployment.
    |
    */

    'device_api_key' => env('DEVICE_API_KEY'),

    'ingest_url' => rtrim((string) env('ESP32CAM_INGEST_URL', ''), '/'),

    'telemetry_interval' => (int) env('ESP32CAM_TELEMETRY_INTERVAL', 10),

    /*
    |--------------------------------------------------------------------------
    | Fill threshold
    |--------------------------------------------------------------------------
    |
    | Percentage at or above which a compartment is reported as full and an
    | alert is opened. Read by App\Support\FillLevelMonitor.
    |
    */

    'fill_threshold' => (float) env('ESP32CAM_FILL_THRESHOLD', 85),

    /*
    | Warning tier. Between this and fill_threshold a bin is "Near Full" and
    | the DSS column recommends scheduling a pickup; at or above
    | fill_threshold it is "Full" and recommends collecting immediately.
    | A bin is also only considered back to normal below this line.
    */
    'fill_warn_threshold' => (float) env('ESP32CAM_WARN_THRESHOLD', 75),

    /*
    |--------------------------------------------------------------------------
    | Real-time stream
    |--------------------------------------------------------------------------
    |
    | Seconds a single /api/live-stream response stays open before the browser
    | reconnects. Bounded so a long-lived SSE connection cannot pin an Apache
    | or php-fpm worker forever.
    |
    */

    'stream_ttl' => (int) env('LIVE_STREAM_TTL', 60),
    'stream_interval_ms' => 1000,

    'chunk_size' => 8192,

];
