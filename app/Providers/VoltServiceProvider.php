<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Volt\Volt;

class VoltServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register the volt-livewire namespace BEFORE Volt::mount
        $livewireViewPath = config('livewire.view_path', resource_path('views/livewire'));
        $this->app['view']->addNamespace('volt-livewire', $livewireViewPath);

        Volt::mount([
            $livewireViewPath,
            resource_path('views/components'),
            resource_path('views/pages'),
            resource_path('views/layouts'),
        ]);
    }
}
