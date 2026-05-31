<?php

use App\Models\HarvesterAssignment;
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
class extends Component {
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

    #[Computed]
    public function year (): int
    {
        return $this->upload->date_from->year;
    }

    #[Computed]
    public function invalidRecords ()
    {
        $query = HarvestRecordStaging::where('upload_id', $this->upload->id)
            ->where('status', 'invalid')
            ->when($this->selectedReason !== 'all', fn($q) => $q->where('validation_reason', 'like', "%$this->selectedReason%"))
            ->orderBy($this->sortBy, $this->sortDirection);

        if ($this->perPage === 0) {
            return $query->get();
        }

        return $query->paginate($this->perPage);
    }

    #[Computed]
    public function validNumbers (): Collection
    {
        return HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->where('year', $this->year)
            ->with('harvester')
            ->orderBy('number')
            ->get();
    }

    #[Computed]
    public function harvestersByNumber ()
    {
        return $this->validNumbers->keyBy('number');
    }

    #[Computed]
    public function validTares (): array
    {
        $fromRecords = HarvestRecord::where('upload_id', $this->upload->id)
            ->distinct()->pluck('tare');

        $fromStaging = HarvestRecordStaging::where('upload_id', $this->upload->id)
            ->distinct()->pluck('tare');

        return $fromRecords->merge($fromStaging)
            ->unique()
            ->filter(fn($t) => $t > 0)
            ->sort()
            ->values()
            ->toArray();
    }

    public function mount (): void
    {
        $this->perPage = auth()->user()->userSettings?->default_per_page ?? 25;
    }

    public function updatedPerPage (): void
    {
        $this->resetPage();
        $this->selectedIds = [];
        $this->selectAll = false;
    }

    public function sort (string $column): void
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

    public function updatedSelectedReason (): void
    {
        $this->resetPage();
        $this->selectedIds = [];
        $this->selectAll = false;
    }

    public function updatedSelectAll (): void
    {
        $records = $this->perPage > 0 ? $this->invalidRecords->items() : $this->invalidRecords->all();
        $this->selectedIds = $this->selectAll
            ? collect($records)->pluck('id')->map(fn($id) => (string) $id)->toArray()
            : [];
    }

    public function resolve (int $recordId): void
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
            $this->validate(
                ["correctedTares.$recordId" => ['required', 'numeric', 'min:0']],
                ["correctedTares.$recordId.required" => __('Tare value is required.')]
            );
            $tare = (float) ($this->correctedTares[$recordId] ?? $stagingRecord->tare);
            $weight = $stagingRecord->gross - $tare;
        }

        HarvestRecord::create([
            'company_id' => $stagingRecord->company_id,
            'upload_id' => $stagingRecord->upload_id,
            'product_id' => $stagingRecord->product_id,
            'harvester_number' => $harvesterNumber,
            'weight' => $weight,
            'tare' => $tare,
            'gross' => $stagingRecord->gross,
            'weighed_at' => $stagingRecord->weighed_at,
            'corrected' => true,
        ]);

        $stagingRecord->update(['status' => 'valid']);
        $stagingRecord->delete();

        unset($this->corrections[$recordId], $this->correctedTares[$recordId]);
        $this->dispatch('$refresh');

        Flux::toast(text: __('Record updated and promoted.'), variant: 'success');
    }

    public function resolveSelected (): void
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
                $validator = Validator::make(
                    ['bulkTare' => $this->bulkTare],
                    ['bulkTare' => ['required', 'numeric', 'min:0']]
                );
                if ($validator->fails()) {
                    $skipped++;

                    continue;
                }
                $tare = (float) $this->bulkTare;
                $weight = $stagingRecord->gross - $tare;
            }

            HarvestRecord::create([
                'company_id' => $stagingRecord->company_id,
                'upload_id' => $stagingRecord->upload_id,
                'product_id' => $stagingRecord->product_id,
                'harvester_number' => $harvesterNumber,
                'weight' => $weight,
                'tare' => $tare,
                'gross' => $stagingRecord->gross,
                'weighed_at' => $stagingRecord->weighed_at,
                'corrected' => true,
            ]);

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
        @if($this->invalidRecords->isEmpty())
            <flux:callout type="success" icon="check-circle" title="{{ __('All Clear') }}">
                {{ __('All harvester numbers are valid for :year.', ['year' => $this->year]) }}
            </flux:callout>
        @else
            <div class="flex items-center justify-between mb-6">
                <flux:text variant="subtle">
                    {{ __(':count record(s) need correction', ['count' => $this->perPage > 0 ? $this->invalidRecords->total() : $this->invalidRecords->count()]) }}
                </flux:text>
                <flux:select wire:model.live="perPage" size="sm" class="w-28">
                    <flux:select.option value="25">25</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                    <flux:select.option value="100">100</flux:select.option>
                    <flux:select.option value="0">{{ __('All') }}</flux:select.option>
                </flux:select>
            </div>

            <div class="mb-6">
                <flux:radio.group wire:model.live="selectedReason" label="{{ __('Reason') }}" variant="pills">
                    <flux:radio value="all" label="{{ __('All') }}"/>
                    <flux:radio value="harvester_not_found" label="{{ __('Harvester Not Found') }}"/>
                    <flux:radio value="tare_out_of_range" label="{{ __('Tare Out of Range') }}"/>
                </flux:radio.group>
            </div>

            @if(!empty($selectedIds))
                <div class="flex flex-wrap items-end gap-4 p-4 bg-zinc-50 dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700 mb-4">
                    <flux:text class="font-medium">{{ __(':count selected', ['count' => count($selectedIds)]) }}</flux:text>
                    <flux:field>
                        <flux:label>{{ __('Harvester # (for harvester errors)') }}</flux:label>
                        <flux:input wire:model="bulkHarvesterNumber" type="number" min="1" max="200" placeholder="#" size="sm" class="w-28"/>
                        <flux:error name="bulkHarvesterNumber"/>
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Tare (for tare errors)') }}</flux:label>
                        <flux:input wire:model="bulkTare" type="number" step="0.001" min="0" placeholder="0.000" size="sm" class="w-32"/>
                        <flux:error name="bulkTare"/>
                    </flux:field>
                    <flux:button variant="primary" size="sm" wire:click="resolveSelected" wire:loading.attr="disabled">
                        <span wire:loading.remove>{{ __('Resolve Selected') }}</span>
                        <span wire:loading>{{ __('Resolving...') }}</span>
                    </flux:button>
                </div>
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
                        <flux:table.row>
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
                                            placeholder="0.000"
                                            size="sm"
                                            class="w-28"
                                        />
                                        <flux:tooltip.content>
                                            @foreach($this->validTares as $tare)
                                                <div>{{ number_format($tare, 3, ',', '.') }}</div>
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
                                            @foreach($this->validNumbers as $assignment)
                                                <div>{{ $assignment->number }}# - {{ $assignment->harvester?->name }}</div>
                                            @endforeach
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
    </div>
</flux:main>
