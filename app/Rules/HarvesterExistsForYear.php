<?php

namespace App\Rules;

use App\Models\HarvesterAssignment;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

readonly class HarvesterExistsForYear implements ValidationRule
{
    public function __construct(
        private int $companyId,
        private Carbon $weighedAt,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = HarvesterAssignment::where('company_id', $this->companyId)
            ->where('year', $this->weighedAt->year)
            ->where('number', $value)
            ->exists();

        if (! $exists) {
            $fail("Harvester :attribute does not exist for year {$this->weighedAt->year}.");
        }
    }
}
