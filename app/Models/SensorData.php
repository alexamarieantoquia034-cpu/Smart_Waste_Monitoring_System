<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SensorData extends Model
{
    protected $table = 'sensor_data';

    protected $fillable = [
        'plastic_level',
        'paper_level',
        'biodegradable_level',
        'reject_level',
        
        'plastic_distance',
        'paper_distance',
        'biodegradable_distance',
        'reject_distance',
    ];

    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }

    public function classificationLogs()
    {
        return $this->hasMany(ClassificationLog::class);
    }
}