<?php

use App\Http\Controllers\Harvest\DownloadHarvestersController;
use App\Http\Controllers\Harvest\DownloadVotersController;
use App\Http\Controllers\Harvest\PrintPayslipsController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('dashboard', 'dashboard')->name('dashboard');

    Volt::route('harvest/harvesters', 'harvest.harvesters')->name('harvesters.index');
    Volt::route('harvest/prices', 'harvest.prices')->name('harvest.prices');
    Volt::route('harvest/upload', 'harvest.upload')->name('harvest.upload');
    Volt::route('harvest/upload/{upload}', 'harvest.upload-review')->name('harvest.upload.review');
    Volt::route('harvest/upload/{upload}/view', 'harvest.upload-view')->name('harvest.upload.view');
    Volt::route('harvest/reports', 'harvest.reports')->name('harvest.reports');
    Volt::route('harvest/charts', 'harvest.charts')->name('harvest.charts');
    Volt::route('harvest/payslip', 'harvest.payslip')->name('harvest.payslip');
    Route::get('harvest/print-payslips', PrintPayslipsController::class)->name('harvest.print-payslips');
    Route::get('harvest/voters/download', DownloadVotersController::class)->name('harvest.voters.download');
    Route::get('harvest/harvesters/download', DownloadHarvestersController::class)->name('harvest.harvesters.download');
});

require __DIR__.'/settings.php';
