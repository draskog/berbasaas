<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Volt::route('harvest/harvesters', 'pages.harvest.harvesters')->name('harvesters.index');
    Volt::route('harvest/prices', 'pages.harvest.prices')->name('harvest.prices');
    Volt::route('harvest/upload', 'pages.harvest.upload')->name('harvest.upload');
    Volt::route('harvest/reports', 'pages.harvest.reports')->name('harvest.reports');
    Volt::route('harvest/charts', 'pages.harvest.charts')->name('harvest.charts');
    Volt::route('harvest/payslip', 'pages.harvest.payslip')->name('harvest.payslip');
});

require __DIR__.'/settings.php';
