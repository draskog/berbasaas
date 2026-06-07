<?php

namespace App\Models;

use App\Enums\ImportType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'product_id', 'uploaded_by', 'original_filename', 'record_count', 'date_from', 'date_to', 'resolved_at', 'import_type'])]
class HarvestUpload extends Model
{
    use HasFactory;

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'resolved_at' => 'datetime',
        'import_type' => ImportType::class,
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function harvestRecords(): HasMany
    {
        return $this->hasMany(HarvestRecord::class, 'upload_id');
    }

    public function stagingRecords(): HasMany
    {
        return $this->hasMany(HarvestRecordStaging::class, 'upload_id');
    }
}
