<?php

namespace App\Http\Controllers;

use App\Models\SensorData;
use App\Models\ClassificationLog;
use App\Models\Alert;

class DashboardController extends Controller
{
    public function index()
{
    $sensorData = SensorData::latest()->first();

    $classification = ClassificationLog::latest()->first();

    $alerts = Alert::where('is_resolved', false)
        ->latest()
        ->get();

    $chartData = SensorData::latest()
        ->take(10)
        ->get()
        ->reverse();

    $labels = $chartData->pluck('created_at')
        ->map(fn($date) => $date->format('H:i'))
        ->values();

    $plasticData = $chartData->pluck('plastic_level')->values();

    $paperData = $chartData->pluck('paper_level')->values();

    $bioData = $chartData->pluck('biodegradable_level')->values();

    $rejectData = $chartData->pluck('reject_level')->values();

    return view('dashboard.index', compact(
        'sensorData',
        'classification',
        'alerts',
        'chartData',
        'labels',
        'plasticData',
        'paperData',
        'bioData',
        'rejectData'
    ));
}
}