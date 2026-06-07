<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeatherRecord extends Model
{
    protected $fillable = [
        'company_id',
        'date',
        'temperature_min',
        'temperature_max',
        'precipitation_sum',
        'wind_speed_max',
        'weather_code',
        'hourly_precipitation',
        'fetched_at',
    ];

    protected $casts = [
        'date' => 'date',
        'temperature_min' => 'float',
        'temperature_max' => 'float',
        'precipitation_sum' => 'float',
        'wind_speed_max' => 'float',
        'hourly_precipitation' => 'array',
        'fetched_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
