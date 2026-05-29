<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'pages.settings.profile')->name('profile.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('settings/appearance', 'pages.settings.appearance')->name('appearance.edit');

    Volt::route('settings/security', 'pages.settings.security')
        ->middleware([
            'password.confirm',
        ])
        ->name('security.edit');
});
