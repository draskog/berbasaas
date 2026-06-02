<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'upload_id', 'product_id', 'harvester_number', 'weight', 'tare', 'gross', 'weighed_at', 'sequence_number', 'duplicate_of_sequence', 'status'])]
class HarvestRecordStaging extends Model
{
    protected $table = 'harvest_record_staging';

    protected function casts(): array
    {
        return [
            'weighed_at' => 'datetime',
            'validation_reason' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function upload(): BelongsTo
    {
        return $this->belongsTo(HarvestUpload::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
