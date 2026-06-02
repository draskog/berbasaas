<?php

use App\Models\HarvesterAssignment;
use App\Models\HarvestRecord;
use App\Models\HarvestRecordStaging;
use App\Models\HarvestUpload;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('View Upload')]
class extends Component
{
    use WithPagination;

    public HarvestUpload $upload;

    public int $perPage = 25;

    public string $activeTab = 'staging';

    public string $sortBy = 'weighed_at';

    public string $sortDirection = 'asc';

    public string $search = '';

    public string $stagingStatus = 'all';

    public string $stagingReason = 'all';

    public string $harvestCorrected = 'all';

    #[Computed]
    public function year(): int
    {
        return $this->upload->date_from->year;
    }

    #[Computed]
    public function stagingRecordsCount(): int
    {
        return HarvestRecordStaging::where('upload_id', $this->upload->id)
            ->when($this->stagingStatus !== 'all', fn ($q) => $q->where('status', $this->stagingStatus))
            ->when($this->stagingReason !== 'all', fn ($q) => $q->where('validation_reason', 'like', "%$this->stagingReason%"))
            ->when($this->search !== '', fn ($q) => $q->where('harvester_number', 'like', "%$this->search%"))
            ->count();
    }

    #[Computed]
    public function stagingRecords()
    {
        $query = HarvestRecordStaging::where('upload_id', $this->upload->id)
            ->when($this->stagingStatus !== 'all', fn ($q) => $q->where('status', $this->stagingStatus))
            ->when($this->stagingReason !== 'all', fn ($q) => $q->where('validation_reason', 'like', "%$this->stagingReason%"))
            ->when($this->search !== '', fn ($q) => $q->where('harvester_number', 'like', "%$this->search%"))
            ->orderBy($this->sortBy, $this->sortDirection);

        if ($this->perPage === 0) {
            return $query->get();
        }

        return $query->paginate($this->perPage, pageName: 'staging_page');
    }

    #[Computed]
    public function harvestRecordsCount(): int
    {
        return HarvestRecord::where('upload_id', $this->upload->id)
            ->when($this->harvestCorrected === 'corrected', fn ($q) => $q->where('corrected', true))
            ->when($this->harvestCorrected === 'not_corrected', fn ($q) => $q->where('corrected', false))
            ->when($this->search !== '', fn ($q) => $q
                ->where('harvester_number', 'like', "%$this->search%")
                ->orWhereRaw("EXISTS (
                    SELECT 1 FROM harvester_assignments ha
                    JOIN harvesters h ON ha.harvester_id = h.id
                    WHERE ha.number = harvest_records.harvester_number
                    AND ha.company_id = harvest_records.company_id
                    AND ha.year = ?
                    AND h.name LIKE ?
                )", [$this->year, "%$this->search%"])
            )
            ->count();
    }

    #[Computed]
    public function harvestRecords()
    {
        $query = HarvestRecord::where('upload_id', $this->upload->id)
            ->when($this->harvestCorrected === 'corrected', fn ($q) => $q->where('corrected', true))
            ->when($this->harvestCorrected === 'not_corrected', fn ($q) => $q->where('corrected', false))
            ->when($this->search !== '', fn ($q) => $q
                ->where('harvester_number', 'like', "%$this->search%")
                ->orWhereRaw("EXISTS (
                    SELECT 1 FROM harvester_assignments ha
                    JOIN harvesters h ON ha.harvester_id = h.id
                    WHERE ha.number = harvest_records.harvester_number
                    AND ha.company_id = harvest_records.company_id
                    AND ha.year = ?
                    AND h.name LIKE ?
                )", [$this->year, "%$this->search%"])
            )
            ->orderBy($this->sortBy, $this->sortDirection);

        if ($this->perPage === 0) {
            return $query->get();
        }

        return $query->paginate($this->perPage, pageName: 'harvest_page');
    }

    #[Computed]
    public function hasStagingRecords(): bool
    {
        return HarvestRecordStaging::where('upload_id', $this->upload->id)->exists();
    }

    #[Computed]
    public function hasHarvestRecords(): bool
    {
        return HarvestRecord::where('upload_id', $this->upload->id)->exists();
    }

    #[Computed]
    public function harvestersByNumber(): Collection
    {
        return HarvesterAssignment::where('company_id', auth()->user()->company_id)
            ->where('year', $this->year)
            ->with('harvester')
            ->orderBy('number')
            ->get()
            ->keyBy('number');
    }

    public function mount(): void
    {
        $this->perPage = auth()->user()->userSettings?->default_per_page ?? 25;
        $this->validateActiveTab();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage('staging_page');
        $this->resetPage('harvest_page');
    }

    public function updatedStagingStatus(): void
    {
        $this->resetPage('staging_page');
    }

    public function updatedStagingReason(): void
    {
        $this->resetPage('staging_page');
    }

    public function updatedHarvestCorrected(): void
    {
        $this->resetPage('harvest_page');
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
        $this->resetPage('staging_page');
        $this->resetPage('harvest_page');
    }

    private function validateActiveTab(): void
    {
        if ($this->activeTab === 'staging' && ! $this->hasStagingRecords) {
            $this->activeTab = $this->hasHarvestRecords ? 'harvest' : '';
        } elseif ($this->activeTab === 'harvest' && ! $this->hasHarvestRecords) {
            $this->activeTab = $this->hasStagingRecords ? 'staging' : '';
        }
    }
}; ?>

<flux:main>
    <flux:header heading="{{ __('View Upload: :filename', ['filename' => $upload->original_filename]) }}">
        <flux:spacer/>
        <a href="{{ route('harvest.upload') }}" wire:navigate>
            <flux:button variant="ghost">{{ __('Back') }}</flux:button>
        </a>
    </flux:header>

    <div class="p-6">
        @php
            $dbDuplicateCount = \App\Models\HarvestRecordStaging::where('upload_id', $upload->id)
                ->where('status', 'invalid')
                ->where('validation_reason', 'like', '%db_duplicate%')
                ->count();
        @endphp

        @if($dbDuplicateCount > 0)
            <flux:callout type="warning" icon="exclamation-circle"
                title="{{ __(':count records are not imported because they already exist in the system', ['count' => $dbDuplicateCount]) }}">
                {{ __('These records were found as duplicates of previously imported records and were not re-imported. They can be deleted or kept as a reference.') }}
            </flux:callout>
        @endif

        @if (!$this->hasStagingRecords && !$this->hasHarvestRecords)
            <flux:callout type="info" icon="information-circle" title="{{ __('Svi duplikati') }}">
                {{ __('Svi zapisi iz ovog fajla su bili duplikati prethodno importovanih zapisa i nisu ponovo uvezeni.') }}
            </flux:callout>
        @else
            <flux:tab.group>
                <flux:tabs wire:model.live="activeTab">
                    @if ($this->hasStagingRecords)
                        <flux:tab name="staging" icon="inbox-stack">{{ __('Staging Records') }}</flux:tab>
                    @endif
                    @if ($this->hasHarvestRecords)
                        <flux:tab name="harvest" icon="check-circle">{{ __('Harvest Records') }}</flux:tab>
                    @endif
                </flux:tabs>

            <flux:tab.panel name="staging">
                <div class="space-y-6">
                    <div class="space-y-4">
                        <div>
                            <flux:radio.group wire:model.live="stagingStatus" label="{{ __('Status') }}" variant="pills">
                                <flux:radio value="all" label="{{ __('All') }}"/>
                                <flux:radio value="pending" label="{{ __('Pending') }}"/>
                                <flux:radio value="valid" label="{{ __('Valid') }}"/>
                                <flux:radio value="invalid" label="{{ __('Invalid') }}"/>
                            </flux:radio.group>
                        </div>
                        <div>
                            <flux:radio.group wire:model.live="stagingReason" label="{{ __('Reason') }}" variant="pills">
                                <flux:radio value="all" label="{{ __('All') }}"/>
                                <flux:radio value="harvester_not_found" label="{{ __('Harvester not found') }}"/>
                                <flux:radio value="tare_out_of_range" label="{{ __('Tare out of range') }}"/>
                                <flux:radio value="in_file_duplicate" label="{{ __('In-file Duplicate') }}"/>
                                <flux:radio value="db_duplicate" label="{{ __('DB Duplicate') }}"/>
                            </flux:radio.group>
                        </div>
                        <div class="flex justify-between items-center">
                            <flux:input type="search" size="sm" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by harvester number...') }}" icon="magnifying-glass" class="w-72!"/>
                            <flux:select wire:model.live="perPage" size="sm" class="w-28">
                                <flux:select.option value="25">25</flux:select.option>
                                <flux:select.option value="50">50</flux:select.option>
                                <flux:select.option value="100">100</flux:select.option>
                                <flux:select.option value="0">{{ __('All') }}</flux:select.option>
                            </flux:select>
                        </div>
                    </div>

                    @if ($this->stagingRecordsCount === 0)
                        <flux:callout type="info" icon="information-circle">
                            {{ __('No staging records match the current filter.') }}
                        </flux:callout>
                    @else
                    <flux:table :paginate="$this->perPage > 0 ? $this->stagingRecords : null" pageName="staging_page">
                        <flux:table.columns>
                            <flux:table.column sortable :sorted="$sortBy === 'weighed_at'" :direction="$sortDirection" wire:click="sort('weighed_at')">{{ __('Date / Time') }}</flux:table.column>
                            <flux:table.column sortable :sorted="$sortBy === 'weight'" :direction="$sortDirection" wire:click="sort('weight')">{{ __('Weight (kg)') }}</flux:table.column>
                            <flux:table.column>{{ __('Tare (kg)') }}</flux:table.column>
                            <flux:table.column>{{ __('Gross (kg)') }}</flux:table.column>
                            <flux:table.column>{{ __('Harvester #') }}</flux:table.column>
                            <flux:table.column>{{ __('Status') }}</flux:table.column>
                            <flux:table.column>{{ __('Reason') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @forelse($this->stagingRecords as $record)
                                <flux:table.row>
                                    <flux:table.cell>{{ $record->weighed_at->format('d.m.Y H:i') }}</flux:table.cell>
                                    <flux:table.cell>{{ number_format($record->weight, 3, ',', '.') }}</flux:table.cell>
                                    <flux:table.cell>{{ number_format($record->tare, 3, ',', '.') }}</flux:table.cell>
                                    <flux:table.cell>{{ number_format($record->gross, 3, ',', '.') }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge variant="warning">{{ $record->harvester_number }}</flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if($record->status === 'pending')
                                            <flux:badge variant="zinc">{{ __('Pending') }}</flux:badge>
                                        @elseif($record->status === 'valid')
                                            <flux:badge variant="success">{{ __('Valid') }}</flux:badge>
                                        @elseif($record->status === 'invalid')
                                            <flux:badge variant="danger">{{ __('Invalid') }}</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @php $reasons = (array) $record->validation_reason; @endphp
                                        @foreach($reasons as $reason)
                                            @if($reason === 'harvester_not_found')
                                                <flux:badge variant="warning" size="sm">{{ __('Harvester not found') }}</flux:badge>
                                            @elseif($reason === 'tare_out_of_range')
                                                <flux:badge variant="danger" size="sm">{{ __('Tare out of range') }}</flux:badge>
                                            @elseif($reason === 'in_file_duplicate')
                                                <flux:badge variant="zinc" size="sm">{{ __('In-file Duplicate') }}</flux:badge>
                                            @elseif($reason === 'db_duplicate')
                                                <flux:badge variant="zinc" size="sm">{{ __('DB Duplicate') }}</flux:badge>
                                            @else
                                                <flux:badge variant="zinc" size="sm">{{ $reason }}</flux:badge>
                                            @endif
                                        @endforeach
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="7" class="text-center text-gray-500">{{ __('No staging records found') }}</flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                    @endif
                </div>
            </flux:tab.panel>

            <flux:tab.panel name="harvest">
                <div class="space-y-6">
                    <div>
                        <flux:radio.group wire:model.live="harvestCorrected" label="{{ __('Corrected') }}" variant="pills">
                            <flux:radio value="all" label="{{ __('All') }}"/>
                            <flux:radio value="corrected" label="{{ __('Only corrected') }}"/>
                            <flux:radio value="not_corrected" label="{{ __('Not corrected') }}"/>
                        </flux:radio.group>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <flux:input type="search" size="sm" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by harvester number or name...') }}" icon="magnifying-glass" class="w-72!"/>
                        <flux:select wire:model.live="perPage" size="sm" class="w-28">
                            <flux:select.option value="25">25</flux:select.option>
                            <flux:select.option value="50">50</flux:select.option>
                            <flux:select.option value="100">100</flux:select.option>
                            <flux:select.option value="0">{{ __('All') }}</flux:select.option>
                        </flux:select>
                    </div>
                    <flux:table :paginate="$this->perPage > 0 ? $this->harvestRecords : null" pageName="harvest_page">
                        <flux:table.columns>
                            <flux:table.column sortable :sorted="$sortBy === 'weighed_at'" :direction="$sortDirection" wire:click="sort('weighed_at')">{{ __('Date / Time') }}</flux:table.column>
                            <flux:table.column sortable :sorted="$sortBy === 'weight'" :direction="$sortDirection" wire:click="sort('weight')">{{ __('Weight (kg)') }}</flux:table.column>
                            <flux:table.column>{{ __('Tare (kg)') }}</flux:table.column>
                            <flux:table.column>{{ __('Gross (kg)') }}</flux:table.column>
                            <flux:table.column>{{ __('Harvester') }}</flux:table.column>
                            <flux:table.column>{{ __('Corrected') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @forelse($this->harvestRecords as $record)
                                <flux:table.row>
                                    <flux:table.cell>{{ $record->weighed_at->format('d.m.Y H:i') }}</flux:table.cell>
                                    <flux:table.cell>{{ number_format($record->weight, 3, ',', '.') }}</flux:table.cell>
                                    <flux:table.cell>
                                        @if($record->original_tare !== null)
                                            <div class="flex items-center gap-2 text-sm">
                                                <flux:badge variant="warning">{{ number_format($record->original_tare, 3, ',', '.') }}</flux:badge>
                                                <span>→</span>
                                                <flux:badge variant="success">{{ number_format($record->tare, 3, ',', '.') }}</flux:badge>
                                            </div>
                                        @else
                                            {{ number_format($record->tare, 3, ',', '.') }}
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>{{ number_format($record->gross, 3, ',', '.') }}</flux:table.cell>
                                    <flux:table.cell>
                                        @php
                                            $assignment = $this->harvestersByNumber->get($record->harvester_number);
                                            $hasOriginal = $record->original_harvester_number !== null && $record->original_harvester_number !== $record->harvester_number;
                                        @endphp
                                        @if($hasOriginal)
                                            <div class="flex flex-col gap-1 text-sm">
                                                <div class="flex items-center gap-2">
                                                    <flux:badge variant="warning">{{ $record->original_harvester_number }}</flux:badge>
                                                    <span>→</span>
                                                    <flux:badge variant="success">{{ $record->harvester_number }}</flux:badge>
                                                </div>
                                                @if($assignment)
                                                    <div class="text-xs text-gray-500">{{ $assignment->harvester?->name }}@if($assignment->harvester?->prefix)
                                                            ({{ $assignment->harvester->prefix }})
                                                        @endif</div>
                                                @endif
                                            </div>
                                        @else
                                            <div>
                                                <flux:badge variant="info">{{ $record->harvester_number }}</flux:badge>
                                                @if($assignment)
                                                    <div class="text-xs text-gray-500 mt-1">{{ $assignment->harvester?->name }}@if($assignment->harvester?->prefix)
                                                            ({{ $assignment->harvester->prefix }})
                                                        @endif</div>
                                                @endif
                                            </div>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if($record->corrected)
                                            <flux:badge variant="success">{{ __('Yes') }}</flux:badge>
                                        @else
                                            <flux:badge variant="zinc">{{ __('No') }}</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="6" class="text-center text-gray-500">{{ __('No harvest records found') }}</flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>
            </flux:tab.panel>
            </flux:tab.group>
        @endif
    </div>
</flux:main>
