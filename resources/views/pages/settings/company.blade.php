<?php

use App\Models\Company;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Company settings')]
class extends Component {
    public string $name = '';

    public ?string $address = null;

    public ?string $tax_number = null;

    public ?string $phone = null;

    public ?string $email = null;

    public ?float $latitude = null;

    public ?float $longitude = null;

    public string $geocodeSearch = '';

    public array $geocodeSuggestions = [];

    public function mount (): void
    {
        $company = Auth::user()->company;
        if ($company) {
            $this->name = $company->name;
            $this->address = $company->address;
            $this->tax_number = $company->tax_number;
            $this->phone = $company->phone;
            $this->email = $company->email;
            $this->latitude = $company->latitude;
            $this->longitude = $company->longitude;
        }
    }

    public function updatedGeocodeSearch(): void
    {
        $this->geocodeSuggestions = [];

        if (strlen($this->geocodeSearch) < 2) {
            return;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::get('https://photon.komoot.io/api', [
                'q' => $this->geocodeSearch,
                'limit' => 5,
                'lang' => 'sr',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->geocodeSuggestions = collect($data['features'] ?? [])
                    ->map(fn ($feature) => [
                        'lat' => $feature['geometry']['coordinates'][1],
                        'lon' => $feature['geometry']['coordinates'][0],
                        'label' => $this->buildLabel($feature['properties']),
                    ])
                    ->toArray();
            }
        } catch (\Exception) {
            $this->geocodeSuggestions = [];
        }
    }

    public function selectLocation(float $lat, float $lon): void
    {
        $this->latitude = $lat;
        $this->longitude = $lon;
        $this->geocodeSearch = '';
        $this->geocodeSuggestions = [];
    }

    private function buildLabel(array $properties): string
    {
        $parts = [];
        if (isset($properties['name'])) {
            $parts[] = $properties['name'];
        }
        if (isset($properties['city'])) {
            $parts[] = $properties['city'];
        }
        if (isset($properties['country'])) {
            $parts[] = $properties['country'];
        }

        return implode(', ', $parts);
    }

    public function updateCompanyInformation (): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'tax_number' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        Auth::user()->company->update($validated);

        Flux::toast(text: __('Company information updated.'), variant: 'success');
    }
}; ?>

<flux:main>
    <section class="w-full">
        @include('partials.settings-heading')

        <flux:heading class="sr-only">{{ __('Company settings') }}</flux:heading>

        <x-pages::settings.layout :heading="__('Company')" :subheading="__('Manage your company information')">
            <form wire:submit="updateCompanyInformation" class="my-6 w-full space-y-6">
                <flux:input wire:model="name" :label="__('Company Name')" type="text" required autofocus/>

                <div class="space-y-2">
                    <label class="block text-sm font-medium text-zinc-900 dark:text-white">
                        {{ __('Address') }}
                    </label>
                    <div class="flex gap-2">
                        <flux:input wire:model="address" type="text" class="flex-1"/>
                        <flux:button
                            wire:click="findLocationFromAddress"
                            type="button"
                            variant="ghost"
                            class="whitespace-nowrap"
                        >
                            {{ __('Find Location') }}
                        </flux:button>
                    </div>
                </div>

                <flux:input wire:model="tax_number" :label="__('Tax Number')" type="text"/>

                <flux:input wire:model="phone" :label="__('Phone')" type="tel"/>

                <flux:input wire:model="email" :label="__('Email')" type="email"/>

                <!-- Fallback to Maps when no results -->
                @if ($addressSearchNoResults)
                    <div class="space-y-3">
                        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-md p-4">
                            <p class="text-sm text-zinc-700 dark:text-zinc-300 mb-3">
                                {{ __('Location not found on service. Open map to find it manually:') }}
                            </p>
                            <div class="flex gap-2">
                                @php
                                    $encodedAddress = urlencode($address ?? '');
                                @endphp
                                <a
                                    href="https://www.openstreetmap.org/search?query={{ $encodedAddress }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition"
                                >
                                    {{ __('OpenStreetMap') }}
                                </a>
                                <a
                                    href="https://maps.google.com/maps/search/{{ $encodedAddress }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-md transition"
                                >
                                    {{ __('Google Maps') }}
                                </a>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Location Geocoding -->
                <div class="space-y-3">
                    <label class="block text-sm font-medium text-zinc-900 dark:text-white">
                        {{ __('Location for weather forecast') }}
                    </label>

                    <div class="relative">
                        <flux:input
                            wire:model.live="geocodeSearch"
                            :placeholder="__('Search address (e.g., city name)...')"
                            type="text"
                            autocomplete="off"
                        />

                        @if (count($geocodeSuggestions) > 0)
                            <div class="absolute z-10 mt-1 w-full bg-white dark:bg-zinc-800 border border-zinc-300 dark:border-zinc-600 rounded-md shadow-lg max-h-40 overflow-y-auto">
                                @foreach ($geocodeSuggestions as $suggestion)
                                    <button
                                        wire:click="selectLocation({{ $suggestion['lat'] }}, {{ $suggestion['lon'] }})"
                                        type="button"
                                        class="w-full text-left px-4 py-2 hover:bg-blue-50 dark:hover:bg-zinc-700 text-sm text-zinc-700 dark:text-zinc-300"
                                    >
                                        {{ $suggestion['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if ($latitude && $longitude)
                        <div class="flex items-center justify-between bg-blue-50 dark:bg-blue-900/30 px-4 py-3 rounded-md border border-blue-200 dark:border-blue-800">
                            <div class="text-sm text-zinc-700 dark:text-zinc-300">
                                <span class="font-medium">{{ __('Location set:') }}</span>
                                {{ number_format($latitude, 4) }}°, {{ number_format($longitude, 4) }}°
                            </div>
                            <button
                                wire:click="$set('latitude', null); $set('longitude', null)"
                                type="button"
                                class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300"
                            >
                                {{ __('Clear') }}
                            </button>
                        </div>
                    @endif

                    <input type="hidden" wire:model="latitude"/>
                    <input type="hidden" wire:model="longitude"/>
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
