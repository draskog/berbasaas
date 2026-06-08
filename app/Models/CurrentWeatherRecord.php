<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurrentWeatherRecord extends Model
{
    protected $fillable = [
        'company_id',
        'time',
        'temperature',
        'weather_code',
        'wind_speed',
        'precipitation',
        'humidity',
        'timezone',
        'fetched_at',
    ];

    protected $casts = [
        'time' => 'datetime',
        'temperature' => 'float',
        'wind_speed' => 'float',
        'precipitation' => 'float',
        'fetched_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
