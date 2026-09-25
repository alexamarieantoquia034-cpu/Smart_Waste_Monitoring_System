<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassificationLog extends Model
{
    protected $fillable = [
        'sensor_data_id',
        'image_path',
        'waste_type',
        'confidence',
        'compartment',
    ];

    public function sensorData()
    {
        return $this->belongsTo(SensorData::class);
    }
}