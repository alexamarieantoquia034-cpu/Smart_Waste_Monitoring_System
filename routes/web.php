<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ClassificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Esp32CamController;
use App\Http\Controllers\LiveStreamController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/alerts', [AlertController::class, 'index'])
    ->middleware(['auth'])
    ->name('alerts');

Route::get('/analytics', [AnalyticsController::class, 'index'])
    ->middleware(['auth'])
    ->name('analytics');

Route::get('/classifications', [ClassificationController::class, 'index'])
    ->middleware(['auth'])
    ->name('classifications.index');

// NOTE: /classifications/live must be declared before /classifications/{}
// or the wildcard route would capture "live" and try to resolve it as a log.
Route::get('/classifications/live', [ClassificationController::class, 'live'])
    ->middleware(['auth'])
    ->name('classifications.live');

Route::get('/classifications/{classification}', [ClassificationController::class, 'show'])
    ->middleware(['auth'])
    ->name('classifications.show');

/*
|--------------------------------------------------------------------------
| Machine Learning + ESP32-CAM
|--------------------------------------------------------------------------
|
| Inference runs client side with TensorFlow.js, so the server only hands
| over the model description, persists the results, and bridges the camera.
|
*/

Route::get('/api/waste-model', [ClassificationController::class, 'model'])
    ->middleware(['auth'])
    ->name('api.waste-model');

Route::post('/api/classifications', [ClassificationController::class, 'store'])
    ->middleware(['auth'])
    ->name('api.classifications.store');

Route::get('/api/esp32cam/stream', [Esp32CamController::class, 'stream'])
    ->middleware(['auth'])
    ->name('api.esp32cam.stream');

Route::get('/api/esp32cam/capture', [Esp32CamController::class, 'capture'])
    ->middleware(['auth'])
    ->name('api.esp32cam.capture');

Route::get('/api/esp32cam/status', [Esp32CamController::class, 'status'])
    ->middleware(['auth'])
    ->name('api.esp32cam.status');

/*
|--------------------------------------------------------------------------
| Real-time updates
|--------------------------------------------------------------------------
|
| Server-Sent Events. The ESP32-CAM posts telemetry and detections on its own
| timer; this pushes them to the browser the moment they are written, so the
| dashboard and the live page update without a refresh.
|
*/

Route::get('/api/live-stream', [LiveStreamController::class, 'stream'])
    ->middleware(['auth'])
    ->name('api.live-stream');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/notifications/unread-count', [AlertController::class, 'unreadCount'])
    ->middleware(['auth'])
    ->name('notifications.unread-count');

Route::view('/reports', 'reports.index')->name('reports');

Route::view('/dss', 'dss.index')->name('dss');

Route::view('/users', 'users.index')->name('users');

Route::view('/settings', 'settings.index')->name('settings');

require __DIR__.'/auth.php';
