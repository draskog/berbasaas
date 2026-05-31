<x-layouts::auth :title="__('Welcome')">
    @php
        $features = [
            ['icon' => 'users', 'label' => 'Harvester Management', 'description' => 'Track and manage harvesters by season and assignment.'],
            ['icon' => 'currency-dollar', 'label' => 'Price Management', 'description' => 'Set and update harvest prices for each period.'],
            ['icon' => 'arrow-up-tray', 'label' => 'CSV Import', 'description' => 'Upload harvest data with automatic validation.'],
            ['icon' => 'magnifying-glass-circle', 'label' => 'Record Review', 'description' => 'Inspect and correct invalid records before finalising.'],
            ['icon' => 'chart-bar', 'label' => 'Reports & Charts', 'description' => 'Visualise harvest performance across seasons.'],
            ['icon' => 'document-text', 'label' => 'Payslip Generation', 'description' => 'Generate payslips for harvesters from validated records.'],
        ];
    @endphp

    <div class="text-center">
        <flux:heading size="xl">Harvest Management</flux:heading>
        <flux:text class="mt-2 text-zinc-500 dark:text-zinc-400 text-sm">
            Streamline your harvest operations — from data import to payslip generation.
        </flux:text>
    </div>

    <flux:separator />

    <div class="flex flex-col gap-2">
        @foreach ($features as $feature)
            <div class="flex items-start gap-3 p-3 rounded-lg bg-zinc-50 dark:bg-zinc-800/50">
                <flux:icon :icon="$feature['icon']" class="size-5 mt-0.5 shrink-0 text-[#72be44]" />
                <div>
                    <flux:text class="font-medium text-sm">{{ $feature['label'] }}</flux:text>
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $feature['description'] }}</flux:text>
                </div>
            </div>
        @endforeach
    </div>

    <div class="flex flex-col gap-2 pt-2">
        @auth
            <flux:button href="{{ route('dashboard') }}" variant="primary" class="w-full" wire:navigate>
                Go to Dashboard
            </flux:button>
        @else
            <flux:button href="{{ route('login') }}" variant="primary" class="w-full" wire:navigate>
                Log In
            </flux:button>
            @if (Route::has('register'))
                <flux:button href="{{ route('register') }}" variant="ghost" class="w-full" wire:navigate>
                    Create Account
                </flux:button>
            @endif
        @endauth
    </div>
</x-layouts::auth>
