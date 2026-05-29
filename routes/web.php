<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('harvest/harvesters', 'pages::harvest.harvesters')->name('harvesters.index');
    Route::livewire('harvest/prices', 'pages::harvest.prices')->name('harvest.prices');
    Route::livewire('harvest/upload', 'pages::harvest.upload')->name('harvest.upload');
    Route::livewire('harvest/reports', 'pages::harvest.reports')->name('harvest.reports');
    Route::livewire('harvest/charts', 'pages::harvest.charts')->name('harvest.charts');
    Route::livewire('harvest/payslip', 'pages::harvest.payslip')->name('harvest.payslip');
});

require __DIR__.'/settings.php';
