<?php

use App\Http\Controllers\ClassificationController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\DashboardController;
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

Route::get('/classifications/{classification}', [ClassificationController::class, 'show'])
    ->middleware(['auth'])
    ->name('classifications.show');

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
