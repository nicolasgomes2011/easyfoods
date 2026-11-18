<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::redirect('/', '/login')->name('home');

// Endereço antigo do painel: leva cada papel à sua tela inicial (UserRole::homeRoute()).
Route::get('dashboard', fn () => redirect()->route(auth()->user()->homeRoute()))
    ->middleware(['auth', 'active', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'active'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/two-factor', 'settings.two-factor')->name('settings.two-factor');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
