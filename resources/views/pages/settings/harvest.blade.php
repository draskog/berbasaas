<?php

use App\Models\HarvestImportSettings;
use App\Models\UserSettings;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new
class extends Component
{
    public int $default_per_page = 25;

    public ?string $tare_min = null;

    public ?string $tare_max = null;

    public function mount(): void
    {
        $this->default_per_page = Auth::user()->userSettings?->default_per_page ?? 25;

        $settings = HarvestImportSettings::where('company_id', Auth::user()->company_id)->first();
        if ($settings) {
            $this->tare_min = $settings->tare_min;
            $this->tare_max = $settings->tare_max;
        }
    }

    public function updateHarvestSettings(): void
    {
        $this->validate([
            'default_per_page' => 'required|integer|in:25,50,100,0',
            'tare_min' => 'nullable|numeric|min:0',
            'tare_max' => 'nullable|numeric|min:0',
        ]);

        if ($this->tare_min !== null && $this->tare_max !== null && $this->tare_min > $this->tare_max) {
            $this->addError('tare_min', 'Minimum tare must be less than or equal to maximum tare.');

            return;
        }

        UserSettings::updateOrCreate(
            ['user_id' => Auth::id()],
            ['default_per_page' => $this->default_per_page]
        );

        HarvestImportSettings::updateOrCreate(
            ['company_id' => Auth::user()->company_id],
            [
                'tare_min' => $this->tare_min ?: null,
                'tare_max' => $this->tare_max ?: null,
            ]
        );

        Flux::toast(text: __('Harvest settings updated.'), variant: 'success');
    }
}; ?>

<flux:main>
    <section class="w-full">
        @include('partials.settings-heading')

        <flux:heading class="sr-only">{{ __('Harvest settings') }}</flux:heading>

        <x-pages::settings.layout :heading="__('Harvest')" :subheading="__('Manage your harvest preferences and validation rules')">
            <form wire:submit="updateHarvestSettings" class="my-6 w-full space-y-6">
                <div>
                    <flux:label>{{ __('Default Records Per Page') }}</flux:label>
                    <flux:text class="mb-2 text-sm text-gray-600 dark:text-zinc-400">
                        {{ __('Choose how many records to display per page by default on all harvest pages.') }}
                    </flux:text>

                    <flux:select wire:model="default_per_page">
                        <flux:select.option value="25">25</flux:select.option>
                        <flux:select.option value="50">50</flux:select.option>
                        <flux:select.option value="100">100</flux:select.option>
                        <flux:select.option value="0">{{ __('All') }}</flux:select.option>
                    </flux:select>
                    <flux:error name="default_per_page" />
                </div>

                <div class="my-8 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                    <flux:label class="mb-2 block">{{ __('Tare Value Range Validation') }}</flux:label>
                    <flux:text class="mb-4 text-sm text-gray-600 dark:text-zinc-400">
                        {{ __('Define the acceptable range for tare values during harvest record imports. Records with tare values outside this range will be marked as invalid and require manual correction.') }}
                    </flux:text>

                    <div class="space-y-4">
                        <flux:input
                            type="number"
                            wire:model="tare_min"
                            :label="__('Minimum Tare (kg)')"
                            step="0.001"
                            placeholder="{{ __('No minimum') }}"
                        />
                        <flux:error name="tare_min" />

                        <flux:input
                            type="number"
                            wire:model="tare_max"
                            :label="__('Maximum Tare (kg)')"
                            step="0.001"
                            placeholder="{{ __('No maximum') }}"
                        />
                        <flux:error name="tare_max" />
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <div class="flex items-center justify-end">
                        <flux:button variant="primary" type="submit" class="w-full">
                            {{ __('Save') }}
                        </flux:button>
                    </div>
                </div>
            </form>
        </x-pages::settings.layout>
    </section>
</flux:main>
