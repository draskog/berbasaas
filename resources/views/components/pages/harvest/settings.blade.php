<?php

use App\Models\HarvestImportSettings;
use App\Models\UserSettings;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Import Settings · eBorovnica')]
class extends Component
{
    public int $defaultPerPage = 25;

    public ?string $tare_min = null;

    public ?string $tare_max = null;

    #[Computed]
    public function settings()
    {
        return HarvestImportSettings::where('company_id', auth()->user()->company_id)->first();
    }

    public function mount(): void
    {
        $this->defaultPerPage = auth()->user()->userSettings?->default_per_page ?? 25;

        $settings = $this->settings;
        if ($settings) {
            $this->tare_min = $settings->tare_min;
            $this->tare_max = $settings->tare_max;
        }
    }

    public function save(): void
    {
        $this->validate([
            'defaultPerPage' => 'required|integer|in:25,50,100,0',
            'tare_min' => 'nullable|numeric|min:0',
            'tare_max' => 'nullable|numeric|min:0',
        ]);

        if ($this->tare_min !== null && $this->tare_max !== null && $this->tare_min > $this->tare_max) {
            $this->addError('tare_min', 'Minimum tare must be less than or equal to maximum tare.');

            return;
        }

        UserSettings::updateOrCreate(
            ['user_id' => auth()->id()],
            ['default_per_page' => $this->defaultPerPage]
        );

        HarvestImportSettings::updateOrCreate(
            ['company_id' => auth()->user()->company_id],
            [
                'tare_min' => $this->tare_min ?: null,
                'tare_max' => $this->tare_max ?: null,
            ]
        );

        Flux::toast(text: 'Settings saved.', variant: 'success');
    }
}; ?>

<flux:main>
    <flux:header heading="Import Settings">
        <flux:spacer />
        <a href="{{ route('harvest.upload') }}" wire:navigate>
            <flux:button variant="ghost">Back</flux:button>
        </a>
    </flux:header>

    <div class="p-6">
        <flux:card>
            <flux:heading size="lg" class="mb-6">Default Records Per Page</flux:heading>

            <flux:text class="mb-6 text-gray-600 dark:text-zinc-400">
                Choose how many records to display per page by default on all harvest pages.
            </flux:text>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>Records Per Page</flux:label>
                    <flux:select wire:model="defaultPerPage">
                        <flux:select.option value="25">25</flux:select.option>
                        <flux:select.option value="50">50</flux:select.option>
                        <flux:select.option value="100">100</flux:select.option>
                        <flux:select.option value="0">All</flux:select.option>
                    </flux:select>
                    <flux:error name="defaultPerPage" />
                </flux:field>

                <div class="mt-6 flex gap-2">
                    <flux:button variant="primary" wire:click="save">Save Settings</flux:button>
                </div>
            </div>
        </flux:card>

        <flux:card class="mt-6">
            <flux:heading size="lg" class="mb-6">Tare Value Range Validation</flux:heading>

            <flux:text class="mb-6 text-gray-600 dark:text-zinc-400">
                Define the acceptable range for tare values during harvest record imports. Records with tare values
                outside this range will be marked as invalid and require manual correction.
            </flux:text>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>Minimum Tare (kg)</flux:label>
                    <flux:input type="number" wire:model="tare_min" step="0.001" placeholder="No minimum" />
                    <flux:error name="tare_min" />
                </flux:field>

                <flux:field>
                    <flux:label>Maximum Tare (kg)</flux:label>
                    <flux:input type="number" wire:model="tare_max" step="0.001" placeholder="No maximum" />
                    <flux:error name="tare_max" />
                </flux:field>

                <div class="mt-6 flex gap-2">
                    <flux:button variant="primary" wire:click="save">Save Settings</flux:button>
                </div>
            </div>
        </flux:card>
    </div>
</flux:main>
