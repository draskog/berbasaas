<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('dashboard', 'dashboard')->name('dashboard');

    Volt::route('harvest/harvesters', 'harvest.harvesters')->name('harvesters.index');
    Volt::route('harvest/prices', 'harvest.prices')->name('harvest.prices');
    Volt::route('harvest/upload', 'harvest.upload')->name('harvest.upload');
    Volt::route('harvest/upload/{upload}', 'harvest.upload-review')->name('harvest.upload.review');
    Volt::route('harvest/reports', 'harvest.reports')->name('harvest.reports');
    Volt::route('harvest/charts', 'harvest.charts')->name('harvest.charts');
    Volt::route('harvest/payslip', 'harvest.payslip')->name('harvest.payslip');
});

require __DIR__.'/settings.php';
