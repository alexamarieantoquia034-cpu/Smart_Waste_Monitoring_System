<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Alert;

class AlertController extends Controller
{
    /**
     * Open alerts, newest first.
     *
     * The ESP32-CAM opens and resolves these through
     * App\Support\FillLevelMonitor as telemetry arrives, so the list is read
     * straight from the database rather than assembled in the view.
     */
    public function index()
    {
        $alerts = Alert::where('is_resolved', false)
            ->latest()
            ->get();

        return view('alerts.index', compact('alerts'));
    }

    public function unreadCount(Request $request)
    {
        return response()->json([
            'count' => Alert::where('is_resolved', false)->count()
        ]);
    }
}
