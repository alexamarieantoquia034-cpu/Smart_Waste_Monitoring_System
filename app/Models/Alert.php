<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    protected $fillable = [
        'sensor_data_id',
        'compartment',
        'status',
        'message',
        'is_resolved',
        'resolved_at',
    ];

    public function sensorData()
    {
        return $this->belongsTo(SensorData::class);
    }
}