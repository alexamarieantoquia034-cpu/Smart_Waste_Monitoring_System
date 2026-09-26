<?php

use App\Http\Controllers\DeviceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Device API
|--------------------------------------------------------------------------
|
| Registered in the "api" group, which carries no session and no CSRF
| middleware, because the ESP32-CAM cannot log in or hold a cookie. Every
| route is gated on the X-Device-Key header by AuthenticateDevice.
|
| These URLs are reachable from the local network, so the key is the only
| thing standing in front of the database.
|
*/

Route::middleware('device')->group(function () {
    // Heartbeat: confirms the key and tells the firmware how often to report.
    Route::get('/device/ping', [DeviceController::class, 'ping'])
        ->name('api.device.ping');

    // Bin levels pushed on the firmware's own timer.
    Route::post('/device/telemetry', [DeviceController::class, 'telemetry'])
        ->name('api.device.telemetry');

    // For a future on-device model; the bundled firmware only streams.
    Route::post('/device/detection', [DeviceController::class, 'detection'])
        ->name('api.device.detection');
});
