<?php

use App\Models\Company;
use App\Models\HarvestImportSettings;
use App\Models\UserSettings;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new
#[Layout('layouts.app')]
#[Title('Settings · eBorovnica')]
class extends Component
{
    public string $userName = '';

    public string $userEmail = '';

    public string $companyName = '';

    public ?string $companyAddress = null;

    public ?string $companyTaxNumber = null;

    public ?string $companyPhone = null;

    public ?string $companyEmail = null;

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
        $this->userName = auth()->user()->name;
        $this->userEmail = auth()->user()->email;

        $company = auth()->user()->company;
        if ($company) {
            $this->companyName = $company->name;
            $this->companyAddress = $company->address;
            $this->companyTaxNumber = $company->tax_number;
            $this->companyPhone = $company->phone;
            $this->companyEmail = $company->email;
        }

        $this->defaultPerPage = auth()->user()->userSettings?->default_per_page ?? 25;

        $settings = $this->settings;
        if ($settings) {
            $this->tare_min = $settings->tare_min;
            $this->tare_max = $settings->tare_max;
        }
    }

    public function saveProfile(): void
    {
        $this->validate([
            'userName' => 'required|string|max:255',
            'userEmail' => 'required|email|max:255|unique:users,email,'.auth()->id(),
        ]);

        auth()->user()->update([
            'name' => $this->userName,
            'email' => $this->userEmail,
        ]);

        Flux::toast(text: 'Profile saved.', variant: 'success');
    }

    public function saveCompany(): void
    {
        $this->validate([
            'companyName' => 'required|string|max:255',
            'companyAddress' => 'nullable|string|max:500',
            'companyTaxNumber' => 'nullable|string|max:100',
            'companyPhone' => 'nullable|string|max:50',
            'companyEmail' => 'nullable|email|max:255',
        ]);

        auth()->user()->company->update([
            'name' => $this->companyName,
            'address' => $this->companyAddress,
            'tax_number' => $this->companyTaxNumber,
            'phone' => $this->companyPhone,
            'email' => $this->companyEmail,
        ]);

        Flux::toast(text: 'Company saved.', variant: 'success');
    }

    public function saveAppSettings(): void
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
    <flux:header heading="Settings">
    </flux:header>

    <div class="p-6">
        <flux:card>
            <flux:heading size="lg" class="mb-6">User Profile</flux:heading>

            <flux:text class="mb-6 text-gray-600 dark:text-zinc-400">
                Update your personal information.
            </flux:text>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>Name</flux:label>
                    <flux:input type="text" wire:model="userName" />
                    <flux:error name="userName" />
                </flux:field>

                <flux:field>
                    <flux:label>Email</flux:label>
                    <flux:input type="email" wire:model="userEmail" />
                    <flux:error name="userEmail" />
                </flux:field>

                <div class="mt-6 flex gap-2">
                    <flux:button variant="primary" wire:click="saveProfile">Save Profile</flux:button>
                </div>
            </div>
        </flux:card>

        <flux:card class="mt-6">
            <flux:heading size="lg" class="mb-6">Company</flux:heading>

            <flux:text class="mb-6 text-gray-600 dark:text-zinc-400">
                Manage your company information.
            </flux:text>

            <div class="space-y-4">
                <flux:field>
                    <flux:label>Company Name</flux:label>
                    <flux:input type="text" wire:model="companyName" />
                    <flux:error name="companyName" />
                </flux:field>

                <flux:field>
                    <flux:label>Address</flux:label>
                    <flux:input type="text" wire:model="companyAddress" placeholder="Optional" />
                    <flux:error name="companyAddress" />
                </flux:field>

                <flux:field>
                    <flux:label>Tax Number</flux:label>
                    <flux:input type="text" wire:model="companyTaxNumber" placeholder="Optional" />
                    <flux:error name="companyTaxNumber" />
                </flux:field>

                <flux:field>
                    <flux:label>Phone</flux:label>
                    <flux:input type="tel" wire:model="companyPhone" placeholder="Optional" />
                    <flux:error name="companyPhone" />
                </flux:field>

                <flux:field>
                    <flux:label>Email</flux:label>
                    <flux:input type="email" wire:model="companyEmail" placeholder="Optional" />
                    <flux:error name="companyEmail" />
                </flux:field>

                <div class="mt-6 flex gap-2">
                    <flux:button variant="primary" wire:click="saveCompany">Save Company</flux:button>
                </div>
            </div>
        </flux:card>

        <flux:card class="mt-6">
            <flux:heading size="lg" class="mb-6">Preferences</flux:heading>

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
                    <flux:button variant="primary" wire:click="saveAppSettings">Save Preferences</flux:button>
                </div>
            </div>
        </flux:card>

        <flux:card class="mt-6">
            <flux:heading size="lg" class="mb-6">Import Settings</flux:heading>

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
                    <flux:button variant="primary" wire:click="saveAppSettings">Save Settings</flux:button>
                </div>
            </div>
        </flux:card>
    </div>
</flux:main>
