<?php

use App\Models\Company;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new
class extends Component {
    public string $name = '';

    public ?string $address = null;

    public ?string $tax_number = null;

    public ?string $phone = null;

    public ?string $email = null;

    public function mount (): void
    {
        $company = Auth::user()->company;
        if ($company) {
            $this->name = $company->name;
            $this->address = $company->address;
            $this->tax_number = $company->tax_number;
            $this->phone = $company->phone;
            $this->email = $company->email;
        }
    }

    public function updateCompanyInformation (): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'tax_number' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
        ]);

        Auth::user()->company->update($validated);

        Flux::toast(variant: 'success', text: __('Company information updated.'));
    }
}; ?>

<flux:main>
    <section class="w-full">
        @include('partials.settings-heading')

        <flux:heading class="sr-only">{{ __('Company settings') }}</flux:heading>

        <x-pages::settings.layout :heading="__('Company')" :subheading="__('Manage your company information')">
            <form wire:submit="updateCompanyInformation" class="my-6 w-full space-y-6">
                <flux:input wire:model="name" :label="__('Company Name')" type="text" required autofocus/>

                <flux:input wire:model="address" :label="__('Address')" type="text"/>

                <flux:input wire:model="tax_number" :label="__('Tax Number')" type="text"/>

                <flux:input wire:model="phone" :label="__('Phone')" type="tel"/>

                <flux:input wire:model="email" :label="__('Email')" type="email"/>

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
