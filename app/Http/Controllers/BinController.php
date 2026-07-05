<?php

namespace App\Http\Controllers;

use App\Models\Bin;

class BinController extends Controller
{
    public function index()
    {
        $bins = Bin::all();
        return view('bins.index', compact('bins'));
    }
}