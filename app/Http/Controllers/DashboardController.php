<?php

namespace App\Http\Controllers;

class DashboardController extends Controller
{
    public function index()
    {
        $sensorData = null;
        $classification = null;
        $alerts = [];
        $recommendation = null;

        return view('dashboard.index', compact(
            'sensorData',
            'classification',
            'alerts',
            'recommendation'
        ));
    }
}