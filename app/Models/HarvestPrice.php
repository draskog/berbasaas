<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['company_id', 'product_id', 'price_per_kg', 'effective_from', 'effective_to'])]
class HarvestPrice extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public static function closeOpenPrecedingPrices(
        int $companyId,
        int $productId,
        Carbon $effectiveFrom,
        ?int $excludeId = null,
    ): void {
        static::where('company_id', $companyId)
            ->where('product_id', $productId)
            ->whereNull('effective_to')
            ->where('effective_from', '<', $effectiveFrom)
            ->when($excludeId !== null, fn ($q) => $q->where('id', '!=', $excludeId))
            ->update(['effective_to' => $effectiveFrom->copy()->subDay()]);
    }
}
