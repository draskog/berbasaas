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
        $livewireViewPath = config('livewire.view_path', resource_path('views/livewire'));

        // Register view namespaces
        $this->app['view']->addNamespace('volt-livewire', $livewireViewPath);
        $this->app['view']->addNamespace('pages', resource_path('views/pages'));
        $this->app['view']->addNamespace('components', resource_path('views/components'));
        $this->app['view']->addNamespace('layouts', resource_path('views/layouts'));

        // Mount Volt directories
        Volt::mount([
            $livewireViewPath,
            resource_path('views/components'),
            resource_path('views/pages'),
            resource_path('views/layouts'),
        ]);
    }
}
