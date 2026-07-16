<?php

use App\Http\Middleware\RedirectIfAppIsInstalled;
use App\Livewire\Dashboard\Genealogy;
use App\Livewire\Dashboard\Network;
use App\Livewire\Dashboard\Overview;
use App\Livewire\Dashboard\Wallet;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Volt::route('install', 'pages.install.wizard')
    ->middleware(RedirectIfAppIsInstalled::class)
    ->name('install');

Route::middleware(['auth', 'verified', 'active'])->group(function () {
    Route::get('dashboard', Overview::class)->name('dashboard');
    Route::get('network', Network::class)->name('network');
    Route::get('genealogy', Genealogy::class)->name('genealogy');
    Route::get('wallet', Wallet::class)->name('wallet');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
