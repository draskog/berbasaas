<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->registerVoltNamespaces();
    }

    protected function registerVoltNamespaces(): void
    {
        $livewireViewPath = config('livewire.view_path', resource_path('views/livewire'));

        $this->app['view']->addNamespace('volt-livewire', $livewireViewPath);
        $this->app['view']->addNamespace('pages', resource_path('views/pages'));
        $this->app['view']->addNamespace('components', resource_path('views/components'));
        $this->app['view']->addNamespace('layouts', resource_path('views/layouts'));

        Volt::mount([
            $livewireViewPath,
            resource_path('views/components'),
            resource_path('views/pages'),
            resource_path('views/layouts'),
        ]);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
