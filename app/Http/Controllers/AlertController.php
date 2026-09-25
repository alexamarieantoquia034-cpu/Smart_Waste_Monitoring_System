<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Alert;

class AlertController extends Controller
{
    public function index()
    {
        // Temporary until IoT is connected
        $alerts = collect();

        return view('alerts.index', compact('alerts'));
    }

    public function unreadCount(Request $request)
    {
        return response()->json([
            'count' => Alert::where('is_resolved', false)->count()
        ]);
    }
}
