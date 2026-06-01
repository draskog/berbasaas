<?php

use App\Models\HarvesterAssignment;
use App\Models\HarvestImportSettings;
use App\Models\HarvestRecord;
use App\Models\HarvestRecordStaging;
use App\Models\HarvestUpload;
use App\Rules\HarvesterExistsForYear;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Session;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Review Upload')]
class extends Component
{
    use WithPagination;

    public HarvestUpload $upload;

    public int $perPage = 25;

    public array $corrections = [];

    public array $correctedTares = [];

    public string $sortBy = 'weighed_at';

    public string $sortDirection = 'asc';

    #[Session]
    public string $selectedReason = 'all';

    public array $selectedIds = [];

    public bool $selectAll = false;

    public string $bulkHarvesterNumber = '';

    public string $bulkTare = '';

    public string $search = '';

    #[Computed]
    public function year(): int
    {
        return $this->upload->date_from->year;
    }

    #[Computed]
    public function invalidRecords()
    {
        $query = HarvestRecordStaging::where('upload_id', $this->upload->id)
            ->where('status', 'invalid')
            ->when($this->selectedReason !== 'all', fn ($q) => $q->where('validation_reason', 'like', "%$this->selectedReason%"))
            ->when($this->search !== '', fn ($q) => $q->where('harvester_number', 'like', "%$this->search%"))
            ->orderBy($this->sortBy, $this->sortDirection);

        if ($this->perPage === 0) {
            return $query->get();
        }

        return $query->paginate($this->perPage);
    }

    #[Computed]
    public function validNumbers(): Collection
    {
        return HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->where('year', $this->year)
            ->with('harvester')
            ->orderBy('number')
            ->get();
    }

    #[Computed]
    public function harvestersByNumber()
    {
        return $this->validNumbers->keyBy('number');
    }

    #[Computed]
    public function validTares(): array
    {
        $fromRecords = HarvestRecord::where('upload_id', $this->upload->id)
            ->distinct()->pluck('tare');

        $fromStaging = HarvestRecordStaging::where('upload_id', $this->upload->id)
            ->distinct()->pluck('tare');

        return $fromRecords->merge($fromStaging)
            ->unique()
            ->filter(fn ($t) => $t > 0)
            ->sort()
            ->values()
            ->toArray();
    }

    #[Computed]
    public function selectedReasonStats(): array
    {
        if (empty($this->selectedIds)) {
            return ['has_harvester' => false, 'has_tare' => false];
        }
        $reasons = HarvestRecordStaging::whereIn('id', $this->selectedIds)
            ->where('company_id', auth()->user()->company_id)
            ->pluck('validation_reason');

        return [
            'has_harvester' => $reasons->some(fn ($r) => in_array('harvester_not_found', (array) $r, true)),
            'has_tare' => $reasons->some(fn ($r) => in_array('tare_out_of_range', (array) $r, true)),
        ];
    }

    #[Computed]
    public function suggestedTaresByRecordId(): array
    {
        $tareErrors = HarvestRecordStaging::where('upload_id', $this->upload->id)
            ->where('status', 'invalid')
            ->when($this->selectedReason !== 'all', fn ($q) => $q->where('validation_reason', 'like', "%$this->selectedReason%"))
            ->get()
            ->filter(fn ($r) => in_array('tare_out_of_range', (array) $r->validation_reason, true));

        if ($tareErrors->isEmpty()) {
            return [];
        }

        $nextSequence = $tareErrors->whereNotNull('sequence_number')
            ->pluck('sequence_number')
            ->map(fn ($n) => $n + 1)
            ->unique();

        if ($nextSequence->isEmpty()) {
            return [];
        }

        $fromStaging = HarvestRecordStaging::where('upload_id', $this->upload->id)
            ->whereIn('sequence_number', $nextSequence)
            ->where('tare', '>', 0)
            ->pluck('tare', 'sequence_number');

        $fromRecords = HarvestRecord::where('upload_id', $this->upload->id)
            ->whereIn('sequence_number', $nextSequence)
            ->where('tare', '>', 0)
            ->pluck('tare', 'sequence_number');

        $nextTareBySequence = $fromStaging->union($fromRecords);

        $result = [];
        foreach ($tareErrors as $record) {
            $nextSeq = $record->sequence_number + 1;
            if ($nextTareBySequence->has($nextSeq)) {
                $result[$record->id] = $nextTareBySequence[$nextSeq];
            }
        }

        return $result;
    }

    #[Computed]
    public function fallbackTare()
    {
        $suggested = $this->suggestedTaresByRecordId;
        if (! empty($suggested)) {
            return reset($suggested);
        }

        return null;
    }

    #[Computed]
    public function importSettings(): ?HarvestImportSettings
    {
        return HarvestImportSettings::where('company_id', auth()->user()->company_id)->first();
    }

    #[Computed]
    public function hasAnyInvalidRecords(): bool
    {
        return HarvestRecordStaging::where('upload_id', $this->upload->id)
            ->where('status', 'invalid')
            ->exists();
    }

    public function mount(): void
    {
        $this->perPage = auth()->user()->userSettings?->default_per_page ?? 25;
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
        $this->selectedIds = [];
        $this->selectAll = false;
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
        $this->selectedIds = [];
        $this->selectAll = false;
    }

    public function updatedSelectedReason(): void
    {
        $this->resetPage();
        $this->selectedIds = [];
        $this->selectAll = false;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->selectedIds = [];
        $this->selectAll = false;
    }

    public function applyTare(int $recordId, float $tare): void
    {
        $this->correctedTares[$recordId] = $tare;
    }

    public function applyHarvesterNumber(int $recordId, int $number): void
    {
        $this->corrections[$recordId] = (string) $number;
    }

    public function updatedSelectAll(): void
    {
        if ($this->selectAll) {
            $this->selectedIds = HarvestRecordStaging::where('upload_id', $this->upload->id)
                ->where('status', 'invalid')
                ->when($this->selectedReason !== 'all', fn ($q) => $q->where('validation_reason', 'like', "%$this->selectedReason%"))
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->toArray();
        } else {
            $this->selectedIds = [];
        }
    }

    public function resolve(int $recordId): void
    {
        $stagingRecord = HarvestRecordStaging::findOrFail($recordId);

        if ($stagingRecord->upload_id !== $this->upload->id || $stagingRecord->company_id !== auth()->user()->company_id) {
            Flux::toast(text: __('Unauthorized access.'), variant: 'danger');

            return;
        }

        $reasons = (array) $stagingRecord->validation_reason;
        $harvesterNumber = $stagingRecord->harvester_number;
        $tare = $stagingRecord->tare;
        $weight = $stagingRecord->weight;

        if (in_array('harvester_not_found', $reasons, true)) {
            $rule = new HarvesterExistsForYear(auth()->user()->company_id, $stagingRecord->weighed_at);
            $this->validate(
                ["corrections.$recordId" => ['required', 'integer', 'min:1', 'max:200', $rule]],
                ["corrections.$recordId.required" => __('Harvester number is required.')]
            );
            $harvesterNumber = (int) ($this->corrections[$recordId] ?? $stagingRecord->harvester_number);
        }

        if (in_array('tare_out_of_range', $reasons, true)) {
            if (empty($this->correctedTares[$recordId]) && $stagingRecord->sequence_number !== null) {
                $suggested = HarvestRecordStaging::where('upload_id', $this->upload->id)
                    ->where('sequence_number', $stagingRecord->sequence_number + 1)
                    ->where('tare', '>', 0)->value('tare')
                    ?? HarvestRecord::where('upload_id', $this->upload->id)
                        ->where('sequence_number', $stagingRecord->sequence_number + 1)
                        ->where('tare', '>', 0)->value('tare');
                if ($suggested !== null) {
                    $this->correctedTares[$recordId] = $suggested;
                }
            }

            $rules = ['required', 'numeric', 'min:0'];
            $settings = $this->importSettings;
            if ($settings?->tare_min !== null) {
                $rules[] = 'min:'.$settings->tare_min;
            }
            if ($settings?->tare_max !== null) {
                $rules[] = 'max:'.$settings->tare_max;
            }
            $this->validate(
                ["correctedTares.$recordId" => $rules],
                ["correctedTares.$recordId.required" => __('Tare value is required.')]
            );
            $tare = (float) ($this->correctedTares[$recordId] ?? $stagingRecord->tare);
            $weight = $stagingRecord->gross - $tare;
        }

        $recordData = [
            'company_id' => $stagingRecord->company_id,
            'upload_id' => $stagingRecord->upload_id,
            'product_id' => $stagingRecord->product_id,
            'harvester_number' => $harvesterNumber,
            'weight' => $weight,
            'tare' => $tare,
            'gross' => $stagingRecord->gross,
            'weighed_at' => $stagingRecord->weighed_at,
            'sequence_number' => $stagingRecord->sequence_number,
            'corrected' => true,
        ];

        if (in_array('harvester_not_found', $reasons, true)) {
            $recordData['original_harvester_number'] = $stagingRecord->harvester_number;
        }

        if (in_array('tare_out_of_range', $reasons, true)) {
            $recordData['original_tare'] = $stagingRecord->tare;
        }

        HarvestRecord::create($recordData);

        $stagingRecord->update(['status' => 'valid']);
        $stagingRecord->delete();

        unset($this->corrections[$recordId], $this->correctedTares[$recordId]);
        $this->dispatch('$refresh');

        Flux::toast(text: __('Record updated and promoted.'), variant: 'success');
    }

    public function resolveSelected(): void
    {
        if (empty($this->selectedIds)) {
            return;
        }

        $records = HarvestRecordStaging::whereIn('id', $this->selectedIds)
            ->where('company_id', auth()->user()->company_id)
            ->where('upload_id', $this->upload->id)
            ->get();

        $resolved = 0;
        $skipped = 0;

        foreach ($records as $stagingRecord) {
            $reasons = (array) $stagingRecord->validation_reason;
            $harvesterNumber = $stagingRecord->harvester_number;
            $tare = $stagingRecord->tare;
            $weight = $stagingRecord->weight;

            if (in_array('harvester_not_found', $reasons, true)) {
                $rule = new HarvesterExistsForYear(auth()->user()->company_id, $stagingRecord->weighed_at);
                $validator = Validator::make(
                    ['bulkHarvesterNumber' => $this->bulkHarvesterNumber],
                    ['bulkHarvesterNumber' => ['required', 'integer', 'min:1', 'max:200', $rule]]
                );
                if ($validator->fails()) {
                    $skipped++;

                    continue;
                }
                $harvesterNumber = (int) $this->bulkHarvesterNumber;
            }

            if (in_array('tare_out_of_range', $reasons, true)) {
                $useTare = $this->bulkTare;
                if (empty($useTare)) {
                    $useTare = $this->suggestedTaresByRecordId[$stagingRecord->id] ?? $this->fallbackTare;
                }
                $rules = ['required', 'numeric', 'min:0'];
                $settings = $this->importSettings;
                if ($settings?->tare_min !== null) {
                    $rules[] = 'min:'.$settings->tare_min;
                }
                if ($settings?->tare_max !== null) {
                    $rules[] = 'max:'.$settings->tare_max;
                }
                $validator = Validator::make(
                    ['bulkTare' => $useTare],
                    ['bulkTare' => $rules]
                );
                if ($validator->fails()) {
                    $skipped++;

                    continue;
                }
                $tare = (float) $useTare;
                $weight = $stagingRecord->gross - $tare;
            }

            $recordData = [
                'company_id' => $stagingRecord->company_id,
                'upload_id' => $stagingRecord->upload_id,
                'product_id' => $stagingRecord->product_id,
                'harvester_number' => $harvesterNumber,
                'weight' => $weight,
                'tare' => $tare,
                'gross' => $stagingRecord->gross,
                'weighed_at' => $stagingRecord->weighed_at,
                'sequence_number' => $stagingRecord->sequence_number,
                'corrected' => true,
            ];

            if (in_array('harvester_not_found', $reasons, true)) {
                $recordData['original_harvester_number'] = $stagingRecord->harvester_number;
            }

            if (in_array('tare_out_of_range', $reasons, true)) {
                $recordData['original_tare'] = $stagingRecord->tare;
            }

            HarvestRecord::create($recordData);

            $stagingRecord->update(['status' => 'valid']);
            $stagingRecord->delete();
            $resolved++;
        }

        $this->selectedIds = [];
        $this->selectAll = false;
        $this->bulkHarvesterNumber = '';
        $this->bulkTare = '';
        $this->dispatch('$refresh');

        $message = $skipped > 0
            ? __('Resolved :resolved record(s), skipped :skipped (missing or invalid input).', ['resolved' => $resolved, 'skipped' => $skipped])
            : __('Resolved :resolved record(s).', ['resolved' => $resolved]);

        Flux::toast(text: $message, variant: $skipped > 0 ? 'warning' : 'success');
    }
}; ?>

<flux:main>
    <flux:header heading="{{ __('Review Upload: :filename', ['filename' => $upload->original_filename]) }}">
        <flux:spacer/>
        <a href="{{ route('harvest.upload') }}" wire:navigate>
            <flux:button variant="ghost">{{ __('Back') }}</flux:button>
        </a>
    </flux:header>

    <div class="p-6">
        @if(!$this->hasAnyInvalidRecords)
            <flux:callout type="success" icon="check-circle" title="{{ __('All Clear') }}">
                {{ __('All records have been corrected.') }}
            </flux:callout>
        @else
            @if(!$this->invalidRecords->isEmpty())
                <div class="flex items-center justify-between gap-4 mb-6">
                    <flux:input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by harvester number...') }}" icon="magnifying-glass" class="flex-1"/>
                    <flux:select wire:model.live="perPage" size="sm" class="w-28">
                        <flux:select.option value="25">25</flux:select.option>
                        <flux:select.option value="50">50</flux:select.option>
                        <flux:select.option value="100">100</flux:select.option>
                        <flux:select.option value="0">{{ __('All') }}</flux:select.option>
                    </flux:select>
                </div>
            @endif

            <div class="mb-6">
                <flux:radio.group wire:model.live="selectedReason" label="{{ __('Reason') }}" variant="pills">
                    <flux:radio value="all" label="{{ __('All') }}"/>
                    <flux:radio value="harvester_not_found" label="{{ __('Harvester not found') }}"/>
                    <flux:radio value="tare_out_of_range" label="{{ __('Tare out of range') }}"/>
                </flux:radio.group>
            </div>
            <div class="mb-6">
                <flux:text variant="subtle">
                    {{ __(':count record(s) need correction', ['count' => $this->perPage > 0 ? $this->invalidRecords->total() : $this->invalidRecords->count()]) }}
                </flux:text>
            </div>

            <flux:callout type="info" icon="information-circle" class="mb-6">
                <div class="text-sm">
                    <div class="font-semibold mb-2">{{ __('How to Resolve') }}</div>
                    <div class="space-y-2">
                        <div>
                            <strong>{{ __('Bulk Resolve') }}</strong>: {{ __('Select records, enter a value, and click "Resolve Selected". Empty tare field uses ★ suggestion automatically.') }}
                        </div>
                        <div>
                            <strong>{{ __('Individual Resolve') }}</strong>: {{ __('Enter a corrected value in each row (or click ★ suggestion in tooltip), then press "Save".') }}
                        </div>
                    </div>
                </div>
            </flux:callout>

            @if($this->invalidRecords->isEmpty())
                <flux:callout type="info" icon="check-circle">
                    {{ __('No records match this filter. Switch the filter above to see remaining errors.') }}
                </flux:callout>
            @else
                @if(!empty($selectedIds))
                    @php $stats = $this->selectedReasonStats; $isMixed = $stats['has_harvester'] && $stats['has_tare']; @endphp
                    @if($isMixed)
                        <flux:callout type="warning" icon="exclamation-triangle" class="mb-4">
                            {{ __('Selected records have different error types (harvester and tare). Please filter by reason first.') }}
                        </flux:callout>
                    @else
                        <div class="flex flex-wrap items-end gap-4 p-4 bg-zinc-50 dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 mb-4">
                            <flux:text class="font-medium">{{ __(':count selected', ['count' => count($selectedIds)]) }}</flux:text>
                            @if($stats['has_harvester'])
                                <flux:field>
                                    <flux:label>{{ __('Harvester #') }}</flux:label>
                                    <flux:input wire:model="bulkHarvesterNumber" type="number" min="1" max="200" placeholder="#" size="sm" class="w-28"/>
                                    <flux:error name="bulkHarvesterNumber"/>
                                </flux:field>
                            @endif
                            @if($stats['has_tare'])
                                <flux:field>
                                    <flux:label>{{ __('Tare') }}</flux:label>
                                    <flux:tooltip>
                                        <flux:input wire:model="bulkTare" type="number" step="0.001" min="0" placeholder="0,000" size="sm" class="w-32"/>
                                        <flux:tooltip.content>
                                            @php
                                                $suggestedTare = $this->fallbackTare;
                                            @endphp
                                            @if($suggestedTare !== null)
                                                <div class="font-semibold text-green-400 cursor-pointer hover:opacity-80 px-1 py-0.5 rounded"
                                                     wire:click="$set('bulkTare', {{ $suggestedTare }})">
                                                    ★ {{ number_format($suggestedTare, 3, ',', '.') }}
                                                </div>
                                            @endif
                                            @foreach($this->validTares as $validTare)
                                                @if($suggestedTare === null || (float)$validTare !== (float)$suggestedTare)
                                                    <div class="cursor-pointer hover:opacity-80 px-1 py-0.5 rounded"
                                                         wire:click="$set('bulkTare', {{ $validTare }})">
                                                        {{ number_format($validTare, 3, ',', '.') }}
                                                    </div>
                                                @endif
                                            @endforeach
                                        </flux:tooltip.content>
                                    </flux:tooltip>
                                    <flux:error name="bulkTare"/>
                                </flux:field>
                            @endif
                            <flux:button variant="primary" size="sm" wire:click="resolveSelected" wire:loading.attr="disabled">
                                <span wire:loading.remove>{{ __('Resolve Selected') }}</span>
                                <span wire:loading>{{ __('Resolving...') }}</span>
                            </flux:button>
                        </div>
                    @endif
                @endif

                <flux:table :paginate="$this->perPage > 0 ? $this->invalidRecords : null">
                <flux:table.columns>
                    <flux:table.column class="w-12">
                        <flux:checkbox wire:model.live="selectAll"/>
                    </flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'weighed_at'" :direction="$sortDirection" wire:click="sort('weighed_at')">{{ __('Date / Time') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'weight'" :direction="$sortDirection" wire:click="sort('weight')">{{ __('Weight (kg)') }}</flux:table.column>
                    <flux:table.column>{{ __('Tare (kg)') }}</flux:table.column>
                    <flux:table.column>{{ __('Corrected Tare') }}</flux:table.column>
                    <flux:table.column>{{ __('Gross (kg)') }}</flux:table.column>
                    <flux:table.column>{{ __('Original #') }}</flux:table.column>
                    <flux:table.column>{{ __('Corrected #') }}</flux:table.column>
                    <flux:table.column>{{ __('Reason') }}</flux:table.column>
                    <flux:table.column>{{ __('Action') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach($this->invalidRecords as $record)
                        <flux:table.row key="record-{{ $record->id }}">
                            <flux:table.cell class="w-12">
                                <flux:checkbox wire:model.live="selectedIds" value="{{ $record->id }}"/>
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $record->weighed_at->format('d.m.Y H:i') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ number_format($record->weight, 3, ',', '.') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ number_format($record->tare, 3, ',', '.') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                @php $reasons = (array) $record->validation_reason; @endphp
                                @if(in_array('tare_out_of_range', $reasons, true))
                                    <flux:tooltip>
                                        <flux:input
                                            wire:model="correctedTares.{{ $record->id }}"
                                            type="number"
                                            step="0.001"
                                            min="0"
                                            placeholder="0,000"
                                            size="sm"
                                            class="w-28"
                                        />
                                        <flux:tooltip.content>
                                            @php
                                                $hasSpecificSuggestion = isset($this->suggestedTaresByRecordId[$record->id]);
                                                $suggestedTare = $this->suggestedTaresByRecordId[$record->id]
                                                    ?? $this->fallbackTare;
                                            @endphp
                                            @if($suggestedTare !== null)
                                                <div class="font-semibold text-green-400 cursor-pointer hover:opacity-80 px-1 py-0.5 rounded"
                                                     wire:click="applyTare({{ $record->id }}, {{ $suggestedTare }})">
                                                    ★ {{ number_format($suggestedTare, 3, ',', '.') }}
                                                </div>
                                            @endif
                                            @foreach($this->validTares as $validTare)
                                                @if((float)$validTare !== (float)($suggestedTare ?? 0))
                                                    <div class="cursor-pointer hover:opacity-80 px-1 py-0.5 rounded"
                                                         wire:click="applyTare({{ $record->id }}, {{ $validTare }})">
                                                        {{ number_format($validTare, 3, ',', '.') }}
                                                    </div>
                                                @endif
                                            @endforeach
                                        </flux:tooltip.content>
                                    </flux:tooltip>
                                    @error('correctedTares.' . $record->id)
                                    <flux:error>{{ $message }}</flux:error>
                                    @enderror
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ number_format($record->gross, 3, ',', '.') }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge variant="warning">{{ $record->harvester_number }}</flux:badge>
                                @if($this->harvestersByNumber->has($record->harvester_number))
                                    <span class="text-sm text-gray-400 ml-1">
                                        {{ $this->harvestersByNumber[$record->harvester_number]->harvester?->name }}
                                    </span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if(in_array('harvester_not_found', $reasons, true))
                                    <flux:tooltip>
                                        <flux:input
                                            wire:model="corrections.{{ $record->id }}"
                                            type="number"
                                            min="1"
                                            max="200"
                                            placeholder="#"
                                            size="sm"
                                            class="w-24"
                                        />
                                        <flux:tooltip.content>
                                            <div x-data="{ search: '' }" class="min-w-48">
                                                <input x-model="search" type="text" placeholder="{{ __('Search...') }}"
                                                       class="w-full text-xs px-2 py-1 mb-1 bg-transparent border-b border-white/20 outline-none"/>
                                                @foreach($this->validNumbers as $assignment)
                                                    <div
                                                        x-show="search === '' || '{{ $assignment->number }} {{ $assignment->harvester?->name }}'.toLowerCase().includes(search.toLowerCase())"
                                                        wire:click="applyHarvesterNumber({{ $record->id }}, {{ $assignment->number }})"
                                                        class="cursor-pointer hover:opacity-80 px-1 py-0.5 rounded"
                                                    >
                                                        {{ $assignment->number }}# - {{ $assignment->harvester?->name }}
                                                    </div>
                                                @endforeach
                                            </div>
                                        </flux:tooltip.content>
                                    </flux:tooltip>
                                    @error('corrections.' . $record->id)
                                    <flux:error>{{ $message }}</flux:error>
                                    @enderror
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @foreach($reasons as $reason)
                                    @if($reason === 'harvester_not_found')
                                        <flux:badge variant="warning" size="sm">{{ __('Harvester not found') }}</flux:badge>
                                    @elseif($reason === 'tare_out_of_range')
                                        <flux:badge variant="danger" size="sm">{{ __('Tare out of range') }}</flux:badge>
                                    @else
                                        <flux:badge variant="zinc" size="sm">{{ $reason }}</flux:badge>
                                    @endif
                                @endforeach
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button
                                    size="sm"
                                    variant="primary"
                                    wire:click="resolve({{ $record->id }})"
                                    wire:loading.attr="disabled"
                                >
                                    {{ __('Save') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
                </flux:table>
            @endif
        @endif
    </div>
</flux:main>
