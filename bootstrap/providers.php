<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\VoltServiceProvider;
use Flux\FluxServiceProvider;
use FluxPro\FluxProServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    VoltServiceProvider::class,
    FluxServiceProvider::class,
    FluxProServiceProvider::class,
];
