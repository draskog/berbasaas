<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'tare_min', 'tare_max', 'csv_delimiter', 'max_bucket_weight'])]
class HarvestImportSettings extends Model
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
