<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'upload_id', 'product_id', 'harvester_number', 'weight', 'tare', 'gross', 'weighed_at'])]
class HarvestRecord extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'weighed_at' => 'datetime',
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
