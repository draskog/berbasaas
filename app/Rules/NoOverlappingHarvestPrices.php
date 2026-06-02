<?php

namespace App\Rules;

use App\Models\HarvestPrice;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

readonly class NoOverlappingHarvestPrices implements ValidationRule
{
    public function __construct(
        private int $companyId,
        private int $productId,
        private Carbon $effectiveFrom,
        private ?Carbon $effectiveTo,
        private ?int $excludeId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Only check overlaps with prices that have a defined end date.
        // Open-ended prices (effective_to IS NULL) are meant to be auto-closed
        // when new prices are created, so we allow overlaps with them.
        $hasOverlap = HarvestPrice::where('company_id', $this->companyId)
            ->where('product_id', $this->productId)
            ->whereNotNull('effective_to')
            ->when($this->excludeId, fn ($q) => $q->where('id', '!=', $this->excludeId))
            ->where(function ($query) {
                // Existing price ends on or after new price starts
                $query->where(function ($q) {
                    $q->whereDate('effective_to', '>=', $this->effectiveFrom);
                })
                // AND existing price starts on or before new price ends (or new price has no end)
                    ->where(function ($q) {
                        if ($this->effectiveTo) {
                            $q->whereDate('effective_from', '<=', $this->effectiveTo);
                        } else {
                            $q->whereDate('effective_from', '<=', now());
                        }
                    });
            })
            ->exists();

        if ($hasOverlap) {
            $fail(__('The effective date range overlaps with an existing price for this product.'));
        }
    }
}
