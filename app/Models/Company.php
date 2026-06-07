<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'address', 'tax_number', 'phone', 'email', 'latitude', 'longitude'])]
class Company extends Model
{
    use HasFactory;

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function harvesters(): HasMany
    {
        return $this->hasMany(Harvester::class);
    }

    public function harvesterAssignments(): HasMany
    {
        return $this->hasMany(HarvesterAssignment::class);
    }

    public function harvestUploads(): HasMany
    {
        return $this->hasMany(HarvestUpload::class);
    }

    public function harvestRecords(): HasMany
    {
        return $this->hasMany(HarvestRecord::class);
    }

    public function harvestPrices(): HasMany
    {
        return $this->hasMany(HarvestPrice::class);
    }

    public function importSettings(): HasOne
    {
        return $this->hasOne(HarvestImportSettings::class);
    }

    public function weatherRecords(): HasMany
    {
        return $this->hasMany(WeatherRecord::class);
    }
}
