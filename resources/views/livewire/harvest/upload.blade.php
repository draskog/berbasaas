<?php

use App\Enums\ImportType;
use App\Models\HarvestImportSettings;
use App\Models\HarvestRecord;
use App\Models\HarvestRecordStaging;
use App\Models\HarvestUpload;
use App\Models\Product;
use App\Services\HarvestImportService;
use App\Services\ManualHarvestImportService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Session;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new
#[Layout('layouts.app')]
#[Title('Harvest Records')]
class extends Component
{
    use WithFileUploads, WithPagination;

    public int $selectedProductId = 0;

    public int $perPage = 25;

    public mixed $uploadedFile = null;

    public array $uploads = [];

    public ?int $deletingUploadId = null;

    public bool $showDeleteModal = false;

    public ?int $archivingUploadId = null;

    public bool $showArchiveModal = false;

    public ?int $resolvingUploadId = null;

    public bool $showResolveModal = false;

    public bool $showUploadModal = false;

    public bool $showManualUploadModal = false;

    public mixed $manualUploadedFile = null;

    public int $manualSelectedProductId = 0;

    public string $manualHarvestDate = '';

    public string $manualTare = '';

    public string $sortBy = 'created_at';

    public string $sortDirection = 'desc';

    public string $search = '';

    #[Session]
    public int $selectedYear = 0;

    #[Session]
    public string $selectedProduct = 'all';

    #[Session]
    public string $selectedStatus = 'all';

    #[Session]
    public string $selectedResolved = 'all';

    #[Session]
    public string $selectedImportType = 'all';

    #[Computed]
    public function products(): Collection
    {
        return Product::where('company_id', auth()->user()->company_id)
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function selectedProducts(): Collection
    {
        return Product::where('company_id', auth()->user()->company_id)
            ->whereHas('harvestUploads', fn ($q) => $q->where('company_id', auth()->user()->company_id))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function availableYears(): array
    {
        return HarvestUpload::where('company_id', auth()->user()->company_id)
            ->pluck('date_from')
            ->map(fn ($date) => $date->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray();
    }

    #[Computed]
    public function availableStatuses(): array
    {
        $query = HarvestUpload::where('company_id', auth()->user()->company_id)
            ->withCount('harvestRecords as valid_count')
            ->withCount('stagingRecords as invalid_count');

        if ($this->selectedYear > 0) {
            $query->whereYear('date_from', $this->selectedYear);
        }

        if ($this->selectedProduct !== 'all') {
            $query->where('product_id', $this->selectedProduct);
        }

        $statuses = [];
        foreach ($query->get() as $upload) {
            if ($upload->valid_count > 0 && $upload->invalid_count === 0) {
                $statuses['valid'] = true;
            } elseif ($upload->valid_count === 0 && $upload->invalid_count > 0) {
                $statuses['invalid'] = true;
            } elseif ($upload->valid_count > 0 && $upload->invalid_count > 0) {
                $statuses['partially_valid'] = true;
            }
            if ($upload->resolved_at !== null && $upload->invalid_count === 0) {
                $statuses['resolved'] = true;
            }
        }

        return array_keys($statuses);
    }

    #[Computed]
    public function availableResolved(): array
    {
        $query = HarvestUpload::where('company_id', auth()->user()->company_id)
            ->withCount('stagingRecords as invalid_count');

        if ($this->selectedYear > 0) {
            $query->whereYear('date_from', $this->selectedYear);
        }

        if ($this->selectedProduct !== 'all') {
            $query->where('product_id', $this->selectedProduct);
        }

        if ($this->selectedStatus !== 'all') {
            $uploads = $query->get();
            $filtered = $uploads->filter(function ($upload) {
                if ($this->selectedStatus === 'valid') {
                    return $upload->valid_count > 0 && $upload->invalid_count === 0;
                } elseif ($this->selectedStatus === 'invalid') {
                    return $upload->valid_count === 0;
                } elseif ($this->selectedStatus === 'partially_valid') {
                    return $upload->valid_count > 0 && $upload->invalid_count > 0;
                } elseif ($this->selectedStatus === 'resolved') {
                    return $upload->resolved_at !== null && $upload->invalid_count === 0;
                }

                return true;
            });
            $query = $filtered;
        } else {
            $query = $query->get();
        }

        $resolved = [];
        foreach ($query as $upload) {
            if ($upload->invalid_count === 0) {
                $resolved['resolved'] = true;
            } else {
                $resolved['unresolved'] = true;
            }
        }

        return array_keys($resolved);
    }

    #[Computed]
    public function availableImportTypes(): array
    {
        $query = HarvestUpload::where('company_id', auth()->user()->company_id)
            ->whereNotNull('import_type')
            ->withCount('harvestRecords as valid_count')
            ->withCount('stagingRecords as invalid_count');

        if ($this->selectedYear > 0) {
            $query->whereYear('date_from', $this->selectedYear);
        }

        if ($this->selectedProduct !== 'all') {
            $query->where('product_id', $this->selectedProduct);
        }

        if ($this->selectedStatus !== 'all') {
            $uploads = $query->get();
            $filtered = $uploads->filter(function ($upload) {
                if ($this->selectedStatus === 'valid') {
                    return $upload->valid_count > 0 && $upload->invalid_count === 0;
                } elseif ($this->selectedStatus === 'invalid') {
                    return $upload->valid_count === 0;
                } elseif ($this->selectedStatus === 'partially_valid') {
                    return $upload->valid_count > 0 && $upload->invalid_count > 0;
                } elseif ($this->selectedStatus === 'resolved') {
                    return $upload->resolved_at !== null && $upload->invalid_count === 0;
                }

                return true;
            });
            $query = $filtered;
        } else {
            $query = $query->get();
        }

        if ($this->selectedResolved === 'resolved') {
            $query = collect($query)->filter(fn ($upload) => $upload->invalid_count === 0);
        } elseif ($this->selectedResolved === 'unresolved') {
            $query = collect($query)->filter(fn ($upload) => $upload->invalid_count > 0);
        }

        return collect($query)
            ->pluck('import_type')
            ->map(fn ($type) => $type instanceof ImportType ? $type->value : $type)
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    #[Computed]
    public function recentUploads()
    {
        $query = HarvestUpload::where('company_id', auth()->user()->company_id)
            ->withCount('harvestRecords as valid_count')
            ->withCount('stagingRecords as invalid_count')
            ->withCount(['stagingRecords as db_duplicate_count' => fn ($q) => $q->where('validation_reason', 'like', '%db_duplicate%')]);

        if ($this->selectedYear > 0) {
            $query->whereYear('date_from', $this->selectedYear);
        }

        if ($this->selectedProduct !== 'all') {
            $query->where('product_id', $this->selectedProduct);
        }

        if ($this->selectedStatus === 'valid') {
            $query->havingRaw('valid_count > 0')
                ->havingRaw('invalid_count = 0');
        } elseif ($this->selectedStatus === 'invalid') {
            $query->havingRaw('valid_count = 0');
        } elseif ($this->selectedStatus === 'partially_valid') {
            $query->havingRaw('valid_count > 0')
                ->havingRaw('invalid_count > 0');
        } elseif ($this->selectedStatus === 'resolved') {
            $query->whereNotNull('resolved_at')
                ->havingRaw('invalid_count = 0');
        }

        if ($this->selectedResolved === 'resolved') {
            $query->whereDoesntHave('stagingRecords', function ($q) {
                $q->where('status', 'invalid');
            });
        } elseif ($this->selectedResolved === 'unresolved') {
            $query->whereHas('stagingRecords', function ($q) {
                $q->where('status', 'invalid');
            });
        }

        if ($this->selectedImportType !== 'all') {
            $query->where('import_type', $this->selectedImportType);
        }

        if ($this->search !== '') {
            $query->where('original_filename', 'like', "%$this->search%");
        }

        $query->orderBy($this->sortBy, $this->sortDirection);

        if ($this->perPage === 0) {
            return $query->get();
        }

        return $query->paginate($this->perPage);
    }

    public function mount(): void
    {
        $this->perPage = auth()->user()->userSettings?->default_per_page ?? 25;
        $currentYear = now()->year;
        $product = $this->products->first();
        if ($product) {
            $this->selectedProductId = $product->id;
        }
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedselectedProduct(): void
    {
        $this->resetPage();
    }

    public function updatedselectedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedselectedResolved(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedImportType(): void
    {
        $this->resetPage();
    }

    public function updatedselectedYear(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
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
    }

    public function uploadFile(): void
    {
        $this->validate([
            'selectedProductId' => 'required|exists:products,id',
            'uploadedFile' => 'required|file|mimes:csv|max:10240',
        ]);

        $service = new HarvestImportService;
        $result = $service->parse(
            $this->uploadedFile,
            auth()->user()->company_id,
            $this->selectedProductId,
            auth()->id()
        );

        $upload = $result['upload'];
        $inFileDuplicateCount = $result['inFileDuplicateCount'] ?? 0;
        $dbDuplicateCount = $result['dbDuplicateCount'] ?? 0;

        $this->uploadedFile = null;
        $this->showUploadModal = false;

        // Reload to get counts
        $upload->loadCount('harvestRecords as valid_count');
        $upload->loadCount(['stagingRecords as invalid_count' => fn ($q) => $q->where('status', 'invalid')]);
        $upload->loadCount(['stagingRecords as resolvable_count' => fn ($q) => $q->where('status', 'invalid')->whereRaw("validation_reason NOT LIKE '%duplicate%' AND validation_reason NOT LIKE '%db_duplicate%'")]);

        $validCount = $upload->valid_count;
        $invalidCount = $upload->invalid_count;
        $resolvableCount = $upload->resolvable_count;

        if ($validCount === 0 && $invalidCount === 0) {
            $message = __('No records could be imported from :filename', [
                'filename' => $upload->original_filename,
            ]);
            $variant = 'warning';
        } else {
            $message = __('Successfully imported :count records from :filename (:from to :to)', [
                'count' => $validCount,
                'filename' => $upload->original_filename,
                'from' => $upload->date_from->format('d.m.Y'),
                'to' => $upload->date_to->format('d.m.Y'),
            ]);

            if ($dbDuplicateCount > 0) {
                $message .= ' '.__('(:db_dup database duplicate record(s) staged)', ['db_dup' => $dbDuplicateCount]);
            }

            if ($inFileDuplicateCount > 0) {
                $message .= ' '.__('(:in_dup in-file duplicate record(s) staged)', ['in_dup' => $inFileDuplicateCount]);
            }

            if ($resolvableCount > 0) {
                $message .= ' '.__('(:resolvable invalid record(s) require review)', ['resolvable' => $resolvableCount]);
            }

            $variant = 'success';
        }

        Flux::toast(text: $message, variant: $variant);
    }

    public function uploadManualFile(): void
    {
        $this->validate([
            'manualSelectedProductId' => 'required|exists:products,id',
            'manualUploadedFile' => 'required|file|mimes:csv|max:10240',
            'manualHarvestDate' => 'required|date_format:Y-m-d',
            'manualTare' => 'required|numeric|min:0',
        ]);

        try {
            $service = new ManualHarvestImportService;
            $result = $service->parse(
                $this->manualUploadedFile,
                auth()->user()->company_id,
                $this->manualSelectedProductId,
                auth()->id(),
                $this->manualHarvestDate,
                $this->manualTare
            );

            $upload = $result['upload'];
            $inFileDuplicateCount = $result['inFileDuplicateCount'] ?? 0;
            $dbDuplicateCount = $result['dbDuplicateCount'] ?? 0;

            $this->manualUploadedFile = null;
            $this->showManualUploadModal = false;

            // Reload to get counts
            $upload->loadCount('harvestRecords as valid_count');
            $upload->loadCount(['stagingRecords as invalid_count' => fn ($q) => $q->where('status', 'invalid')]);
            $upload->loadCount(['stagingRecords as resolvable_count' => fn ($q) => $q->where('status', 'invalid')->whereRaw("validation_reason NOT LIKE '%duplicate%' AND validation_reason NOT LIKE '%db_duplicate%'")]);

            $validCount = $upload->valid_count;
            $invalidCount = $upload->invalid_count;
            $resolvableCount = $upload->resolvable_count;

            if ($validCount === 0 && $invalidCount === 0) {
                $message = __('No records could be imported from :filename', [
                    'filename' => $upload->original_filename,
                ]);
                $variant = 'warning';
            } else {
                $message = __('Successfully imported :count records from :filename', [
                    'count' => $validCount,
                    'filename' => $upload->original_filename,
                ]);

                if ($dbDuplicateCount > 0) {
                    $message .= ' '.__('(:db_dup database duplicate record(s) staged)', ['db_dup' => $dbDuplicateCount]);
                }

                if ($inFileDuplicateCount > 0) {
                    $message .= ' '.__('(:in_dup in-file duplicate record(s) staged)', ['in_dup' => $inFileDuplicateCount]);
                }

                if ($resolvableCount > 0) {
                    $message .= ' '.__('(:resolvable invalid record(s) require review)', ['resolvable' => $resolvableCount]);
                }

                $variant = 'success';
            }

            Flux::toast(text: $message, variant: $variant);
        } catch (\InvalidArgumentException $e) {
            Flux::toast(text: __('Error: :message', ['message' => $e->getMessage()]), variant: 'danger');
        }
    }

    public function downloadManualCsvTemplate()
    {
        $settings = HarvestImportSettings::where('company_id', auth()->user()->company_id)->first();
        $delimiter = $settings?->csv_delimiter ?? ',';

        $rows = [
            ['berac_br', 'bruto_tezina'],
            [1, 2.230],
            [2, 2.800],
            [3, 2.500],
        ];

        $content = '';
        foreach ($rows as $row) {
            $content .= implode($delimiter, $row)."\n";
        }

        return response()
            ->streamDownload(
                fn () => print $content,
                'rucno-branje-templejt.csv',
                [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="rucno-branje-templejt.csv"',
                ]
            );
    }

    public function confirmResolveUpload(int $id): void
    {
        $this->resolvingUploadId = $id;
        $this->showResolveModal = true;
    }

    public function confirmDeleteUpload(int $id): void
    {
        $this->deletingUploadId = $id;
        $this->showDeleteModal = true;
    }

    public function deleteUpload(): void
    {
        HarvestUpload::find($this->deletingUploadId)?->delete();
        $this->deletingUploadId = null;
        $this->showDeleteModal = false;
        $this->dispatch('$refresh');
        Flux::toast(text: __('Upload deleted.'), variant: 'warning');
    }

    public function confirmArchiveUpload(int $id): void
    {
        $this->archivingUploadId = $id;
        $this->showArchiveModal = true;
    }

    public function archiveUpload(): void
    {
        $upload = HarvestUpload::find($this->archivingUploadId);
        if ($upload) {
            HarvestRecord::where('upload_id', $upload->id)->update(['upload_id' => null]);
            $upload->stagingRecords()->delete();
            $upload->delete();
        }
        $this->archivingUploadId = null;
        $this->showArchiveModal = false;
        $this->dispatch('$refresh');
        Flux::toast(text: __('Upload archived.'), variant: 'success');
    }

    public function autoResolve(int $uploadId): void
    {
        $upload = HarvestUpload::findOrFail($uploadId);

        // Authorize access
        if ($upload->company_id !== auth()->user()->company_id) {
            Flux::toast(text: __('Unauthorized access.'), variant: 'danger');

            return;
        }

        $resolved = 0;
        $deleted = 0;

        // Handle duplicates - automatically delete them
        $duplicates = HarvestRecordStaging::where('upload_id', $uploadId)
            ->where('status', 'invalid')
            ->where(function ($q) {
                $q->where('validation_reason', 'like', '%in_file_duplicate%')
                    ->orWhere('validation_reason', 'like', '%db_duplicate%');
            })
            ->get();

        foreach ($duplicates as $record) {
            $record->delete();
            $deleted++;
        }

        // Handle tare_out_of_range errors with suggestions
        $tareErrors = HarvestRecordStaging::where('upload_id', $uploadId)
            ->where('status', 'invalid')
            ->where('validation_reason', 'like', '%tare_out_of_range%')
            ->get();

        $importSettings = HarvestImportSettings::where('company_id', $upload->company_id)->first();

        foreach ($tareErrors as $record) {
            $suggestedTare = null;

            if ($record->sequence_number !== null) {
                $suggestedTare = HarvestRecordStaging::where('upload_id', $uploadId)
                    ->where('sequence_number', $record->sequence_number + 1)
                    ->where('tare', '>', 0)->value('tare')
                    ?? HarvestRecord::where('upload_id', $uploadId)
                        ->where('sequence_number', $record->sequence_number + 1)
                        ->where('tare', '>', 0)->value('tare');
            }

            if ($suggestedTare === null) {
                $allTareErrors = $tareErrors->filter(fn ($r) => $r->sequence_number !== null);
                if ($allTareErrors->isNotEmpty()) {
                    $nextSequence = $allTareErrors->pluck('sequence_number')
                        ->map(fn ($n) => $n + 1)
                        ->unique();

                    $suggestedTare = HarvestRecordStaging::where('upload_id', $uploadId)
                        ->whereIn('sequence_number', $nextSequence)
                        ->where('tare', '>', 0)
                        ->orderBy('sequence_number')
                        ->value('tare')
                        ?? HarvestRecord::where('upload_id', $uploadId)
                            ->whereIn('sequence_number', $nextSequence)
                            ->where('tare', '>', 0)
                            ->orderBy('sequence_number')
                            ->value('tare');
                }
            }

            if ($suggestedTare !== null) {
                $tare = (float) $suggestedTare;
                $validTare = true;

                if ($importSettings?->tare_min !== null && $tare < $importSettings->tare_min) {
                    $validTare = false;
                }
                if ($importSettings?->tare_max !== null && $tare > $importSettings->tare_max) {
                    $validTare = false;
                }

                if ($validTare) {
                    $weight = $record->gross - $tare;
                    HarvestRecord::create([
                        'company_id' => $record->company_id,
                        'upload_id' => $record->upload_id,
                        'product_id' => $record->product_id,
                        'harvester_number' => $record->harvester_number,
                        'weight' => $weight,
                        'tare' => $tare,
                        'gross' => $record->gross,
                        'weighed_at' => $record->weighed_at,
                        'sequence_number' => $record->sequence_number,
                        'corrected' => true,
                        'original_tare' => $record->tare,
                    ]);

                    $record->update(['status' => 'valid']);
                    $record->delete();
                    $resolved++;
                }
            }
        }

        $this->showResolveModal = false;
        $this->resolvingUploadId = null;

        // Mark as resolved if all invalid records are gone
        if ($upload->stagingRecords()->where('status', 'invalid')->doesntExist()) {
            $upload->update(['resolved_at' => now()]);
        }

        $this->dispatch('$refresh');

        if ($resolved === 0 && $deleted === 0) {
            $message = __('No records could be auto-resolved. Please resolve manually.');
            $variant = 'warning';
        } else {
            $parts = [];
            if ($resolved > 0) {
                $parts[] = __(':count resolved', ['count' => $resolved]);
            }
            if ($deleted > 0) {
                $parts[] = __(':count duplicate(s) deleted', ['count' => $deleted]);
            }
            $message = __('Auto-resolved: :summary', ['summary' => implode(', ', $parts)]);
            $variant = 'success';
        }

        Flux::toast(text: $message, variant: $variant);
    }

    private function promoteRecord(HarvestRecordStaging $record, ?int $originalHarvesterNumber = null): void
    {
        $data = [
            'company_id' => $record->company_id,
            'upload_id' => $record->upload_id,
            'product_id' => $record->product_id,
            'harvester_number' => $record->harvester_number,
            'weight' => $record->weight,
            'tare' => $record->tare,
            'gross' => $record->gross,
            'weighed_at' => $record->weighed_at,
            'sequence_number' => $record->sequence_number,
        ];

        if ($originalHarvesterNumber !== null) {
            $data['original_harvester_number'] = $originalHarvesterNumber;
        }

        HarvestRecord::create($data);

        $record->update(['status' => 'valid']);
        $record->delete();

        // Mark upload as resolved if all invalid records are now gone
        $upload = HarvestUpload::find($record->upload_id);
        if ($upload && $upload->stagingRecords()->where('status', 'invalid')->doesntExist()) {
            $upload->update(['resolved_at' => now()]);
        }
    }
}; ?>

<flux:main>
    <flux:header :heading="__('Recent Upload Harvest Records')">
        <flux:spacer/>
        <flux:button variant="primary" size="sm" icon="arrow-up-tray" wire:click="$set('showUploadModal', true)">
            {{ __('Uvezi CSV fajl iz vage') }}
        </flux:button>
        <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="$set('showManualUploadModal', true)">
            {{ __('Uvezi ručni CSV') }}
        </flux:button>
        <flux:button variant="ghost" size="sm" icon="arrow-down-tray" wire:click="downloadManualCsvTemplate">
            {{ __('Preuzmi primer') }}
        </flux:button>
    </flux:header>

    <div class="p-6">
        <div class="space-y-4">
            <div>
                <flux:radio.group wire:model.live="selectedYear" :label="__('Year')" variant="pills">
                    <flux:radio value="0" :label="__('All')"/>
                    @foreach($this->availableYears as $year)
                        <flux:radio value="{{ $year }}" label="{{ $year }}"/>
                    @endforeach
                </flux:radio.group>
            </div>
            <div>
                <flux:radio.group wire:model.live="selectedProduct" :label="__('Product')" variant="pills">
                    <flux:radio value="all" :label="__('All')"/>
                    @foreach($this->selectedProducts as $product)
                        <flux:radio value="{{ $product->id }}" label="{{ $product->name }}"/>
                    @endforeach
                </flux:radio.group>
            </div>
            <div>
                <flux:radio.group wire:model.live="selectedStatus" :label="__('Status')" variant="pills">
                    <flux:radio value="all" :label="__('All')"/>
                    @if(in_array('valid', $this->availableStatuses, true))
                        <flux:radio value="valid" :label="__('Valid')"/>
                    @endif
                    @if(in_array('invalid', $this->availableStatuses, true))
                        <flux:radio value="invalid" :label="__('Invalid')"/>
                    @endif
                    @if(in_array('partially_valid', $this->availableStatuses, true))
                        <flux:radio value="partially_valid" :label="__('Partially Valid')"/>
                    @endif
                    @if(in_array('resolved', $this->availableStatuses, true))
                        <flux:radio value="resolved" :label="__('Resolved')"/>
                    @endif
                </flux:radio.group>
            </div>
            <div>
                <flux:radio.group wire:model.live="selectedResolved" :label="__('Resolution Status')" variant="pills">
                    <flux:radio value="all" :label="__('All')"/>
                    @if(in_array('resolved', $this->availableResolved, true))
                        <flux:radio value="resolved" :label="__('Resolved')"/>
                    @endif
                    @if(in_array('unresolved', $this->availableResolved, true))
                        <flux:radio value="unresolved" :label="__('Unresolved')"/>
                    @endif
                </flux:radio.group>
            </div>
            @if(count($this->availableImportTypes) > 0)
                <div>
                    <flux:radio.group wire:model.live="selectedImportType" :label="__('Tip uvoza')" variant="pills">
                        <flux:radio value="all" :label="__('All')"/>
                        @foreach($this->availableImportTypes as $type)
                            @php
                                $labels = [
                                    'scale_csv' => __('Iz vage'),
                                    'manual_csv' => __('Ručni'),
                                ];
                                $label = $labels[$type] ?? $type;
                            @endphp
                            <flux:radio value="{{ $type }}" :label="$label"/>
                        @endforeach
                    </flux:radio.group>
                </div>
            @endif
            <div class="flex justify-between items-center">
                <flux:input type="search" size="sm" wire:model.live.debounce.300ms="search" :placeholder="__('Search by filename...')" icon="magnifying-glass" class="w-72!"/>
                <flux:select wire:model.live="perPage" size="sm" class="w-28">
                    <flux:select.option value="25">25</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                    <flux:select.option value="100">100</flux:select.option>
                    <flux:select.option value="0">{{ __('All') }}</flux:select.option>
                </flux:select>
            </div>
        </div>

        <flux:table :paginate="$this->perPage > 0 ? $this->recentUploads : null">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sortBy === 'original_filename'" :direction="$sortDirection" wire:click="sort('original_filename')">{{ __('Filename') }}</flux:table.column>
                <flux:table.column>{{ __('Product') }}</flux:table.column>
                <flux:table.column>{{ __('Total') }}</flux:table.column>
                <flux:table.column>{{ __('Valid') }}</flux:table.column>
                <flux:table.column>{{ __('Duplicates') }}</flux:table.column>
                <flux:table.column>{{ __('Invalid') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sortBy === 'created_at'" :direction="$sortDirection" wire:click="sort('created_at')">{{ __('Date Range') }}</flux:table.column>
                <flux:table.column>{{ __('Imported At') }}</flux:table.column>
                <flux:table.column>{{ __('Uploaded By') }}</flux:table.column>
                <flux:table.column align="center">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse($this->recentUploads as $upload)
                    <flux:table.row>
                        <flux:table.cell>{{ $upload->original_filename }}</flux:table.cell>
                        <flux:table.cell>{{ $upload->product->name }}</flux:table.cell>
                        <flux:table.cell>{{ $upload->record_count }}</flux:table.cell>
                        <flux:table.cell>{{ $upload->valid_count }}</flux:table.cell>
                        <flux:table.cell>
                            @if($upload->db_duplicate_count > 0)
                                <flux:badge color="yellow">{{ $upload->db_duplicate_count }}</flux:badge>
                            @else
                                {{ $upload->db_duplicate_count }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($upload->valid_count === 0 && $upload->invalid_count === 0 && $upload->record_count > 0)
                                <flux:badge color="zinc">{{ $upload->record_count }}</flux:badge>
                            @elseif($upload->invalid_count > 0)
                                <flux:badge color="orange">{{ $upload->invalid_count }}</flux:badge>
                            @else
                                {{ $upload->invalid_count }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($upload->resolved_at !== null && $upload->invalid_count === 0)
                                <flux:badge color="blue">{{ __('Resolved') }}</flux:badge>
                            @elseif($upload->valid_count > 0 && $upload->invalid_count === 0)
                                <flux:badge color="green">{{ __('Valid') }}</flux:badge>
                            @elseif($upload->valid_count === 0)
                                <flux:badge color="red">{{ __('Invalid') }}</flux:badge>
                            @else
                                <flux:badge color="orange">{{ __('Partially Valid') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if($upload->date_from->isSameDay($upload->date_to))
                                {{ $upload->date_from->format('d.m.Y') }}
                            @else
                                {{ $upload->date_from->format('d.m.Y') }} - {{ $upload->date_to->format('d.m.Y') }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $upload->created_at->format('d.m.Y H:i') }}</flux:table.cell>
                        <flux:table.cell>{{ $upload->uploadedBy->name }}</flux:table.cell>
                        <flux:table.cell align="end" class="space-x-2">
                            <a href="{{ route('harvest.upload.view', $upload) }}" wire:navigate>
                                <flux:button size="sm" variant="ghost">{{ __('View') }}</flux:button>
                            </a>
                            @if($upload->invalid_count > 0)
                                <flux:button size="sm" variant="primary" wire:click="confirmResolveUpload({{ $upload->id }})">
                                    {{ __('Resolve') }}
                                </flux:button>
                            @endif
                            @if($upload->valid_count > 0)
                                <flux:button size="sm" variant="filled" wire:click="confirmArchiveUpload({{ $upload->id }})">
                                    {{ __('Archive') }}
                                </flux:button>
                            @endif

                            <flux:button variant="danger" size="sm" wire:click="confirmDeleteUpload({{ $upload->id }})">{{ __('Delete') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="text-center text-gray-500">{{ __('No uploads yet') }}</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    @php
        $resolvingUpload = $this->resolvingUploadId ? HarvestUpload::find($this->resolvingUploadId) : null;
    @endphp

    <flux:modal name="resolve-upload" :dismissible="true" wire:model="showResolveModal">
        @if($resolvingUpload)
            <flux:heading>{{ __('Resolve :count invalid record(s)', ['count' => $resolvingUpload->invalid_count]) }}</flux:heading>
            <flux:text class="mt-4">
                {{ __('Choose how to handle the invalid records in this upload.') }}
            </flux:text>

            <flux:callout type="info" icon="information-circle" class="mt-6 mb-6">
                <div class="font-semibold mb-2">{{ __('Auto-Resolve Logic') }}</div>
                <ul class="text-sm space-y-2">
                    <li>{{ __('Tare out of range: uses the tare value from the next sequential record (★ suggestion)') }}</li>
                    <li>{{ __('Duplicates (database or in-file): automatically deleted from staging') }}</li>
                </ul>
                <div class="text-sm mt-3">{{ __('Records that cannot be resolved are skipped and remain for manual review.') }}</div>
            </flux:callout>

            <div class="mt-6 flex flex-col gap-3">
                <flux:button
                    variant="primary"
                    wire:click="autoResolve({{ $resolvingUpload->id }})"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove>{{ __('Resolve Automatically') }}</span>
                    <span wire:loading>{{ __('Resolving...') }}</span>
                </flux:button>
                <a href="{{ route('harvest.upload.review', $resolvingUpload) }}" wire:navigate>
                    <flux:button variant="ghost" class="w-full">{{ __('Resolve Manually') }}</flux:button>
                </a>
            </div>
        @endif
    </flux:modal>

    <flux:modal name="confirm-delete-upload" :dismissible="false" wire:model="showDeleteModal">
        <flux:heading>{{ __('Delete Upload') }}</flux:heading>
        <flux:text>{{ __('Are you sure you want to delete this upload? This cannot be undone.') }}</flux:text>

        <div class="mt-6 flex gap-2 justify-end">
            <flux:button variant="ghost" wire:click="$set('showDeleteModal', false)">{{ __('Cancel') }}</flux:button>
            <flux:button variant="danger" wire:click="deleteUpload">{{ __('Delete') }}</flux:button>
        </div>
    </flux:modal>

    <flux:modal name="confirm-archive-upload" :dismissible="false" wire:model="showArchiveModal">
        <flux:heading>{{ __('Archive Upload') }}</flux:heading>
        <flux:text>{{ __('This will keep all valid harvest records but remove the upload and staging records. This cannot be undone.') }}</flux:text>

        <div class="mt-6 flex gap-2 justify-end">
            <flux:button variant="ghost" wire:click="$set('showArchiveModal', false)">{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="archiveUpload">{{ __('Archive') }}</flux:button>
        </div>
    </flux:modal>

    <flux:modal name="upload-csv" :dismissible="true" wire:model="showUploadModal">
        <flux:heading>{{ __('Upload CSV File') }}</flux:heading>

        <div class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Product') }}</flux:label>
                <flux:select variant="listbox" searchable wire:model="selectedProductId" :placeholder="__('Select a product...')">
                    @foreach($this->products as $product)
                        <flux:select.option value="{{ $product->id }}">{{ $product->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="selectedProductId"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('CSV File') }}</flux:label>
                <flux:input type="file" wire:model="uploadedFile" accept=".csv"/>
                <flux:error name="uploadedFile"/>
            </flux:field>
        </div>

        <div class="mt-6 flex gap-2 justify-end">
            <flux:button variant="ghost" wire:click="$set('showUploadModal', false)">{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="uploadFile" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ __('Upload') }}</span>
                <span wire:loading>{{ __('Uploading...') }}</span>
            </flux:button>
        </div>
    </flux:modal>

    <flux:modal name="manual-upload-csv" :dismissible="true" wire:model="showManualUploadModal">
        <flux:heading>{{ __('Uvezi ručni CSV') }}</flux:heading>

        <div class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Product') }}</flux:label>
                <flux:select variant="listbox" searchable wire:model="manualSelectedProductId" :placeholder="__('Select a product...')">
                    @foreach($this->products as $product)
                        <flux:select.option value="{{ $product->id }}">{{ $product->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="manualSelectedProductId"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Datum berbe') }}</flux:label>
                <flux:input type="date" wire:model="manualHarvestDate"/>
                <flux:error name="manualHarvestDate"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Tare') }}</flux:label>
                <flux:input type="number" wire:model="manualTare" step="0.001" placeholder="0.000"/>
                <flux:error name="manualTare"/>
            </flux:field>

            <flux:field>
                <flux:label>{{ __('CSV File') }}</flux:label>
                <flux:input type="file" wire:model="manualUploadedFile" accept=".csv"/>
                <flux:error name="manualUploadedFile"/>
            </flux:field>
        </div>

        <div class="mt-6 flex gap-2 justify-end">
            <flux:button variant="ghost" wire:click="$set('showManualUploadModal', false)">{{ __('Cancel') }}</flux:button>
            <flux:button variant="primary" wire:click="uploadManualFile" wire:loading.attr="disabled">
                <span wire:loading.remove>{{ __('Upload') }}</span>
                <span wire:loading>{{ __('Uploading...') }}</span>
            </flux:button>
        </div>
    </flux:modal>

</flux:main>
