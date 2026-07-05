<?php

use App\Http\Controllers\BinController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');
   
Route::get('/bins', [BinController::class, 'index']) ->name('bins');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::view('/alerts', 'alerts.index')->name('alerts');

Route::view('/analytics', 'analytics.index')->name('analytics');

Route::view('/reports', 'reports.index')->name('reports');

Route::view('/dss', 'dss.index')->name('dss');

Route::view('/users', 'users.index')->name('users');

Route::view('/settings', 'settings.index')->name('settings');

require __DIR__.'/auth.php';
