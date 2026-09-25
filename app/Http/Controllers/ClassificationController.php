<?php

namespace App\Http\Controllers;

use App\Models\ClassificationLog;

class ClassificationController extends Controller
{
    public function index()
    {
        $classifications = ClassificationLog::latest()->paginate(10);

        return view('classifications.index', compact('classifications'));
    }

    public function show(ClassificationLog $classification)
    {
        return view('classifications.show', compact('classification'));
    }
}