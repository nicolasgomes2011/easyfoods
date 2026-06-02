<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Livewire\Auth\LoginForm;
Route::get('/login', LoginForm::class)->name('login')->middleware('guest');

Route::middleware('guest')->group(function () {
    // O fluxo de redefinição de senha é tratado de ponta a ponta pelos componentes
    // Volt autossuficientes (chamam Password::sendResetLink / Password::reset via
    // wire:submit). O link enviado por e-mail (inclusive no convite de staff) aponta
    // para a rota nomeada 'password.reset'.
    Volt::route('forgot-password', 'auth.forgot-password')
        ->name('password.request');

    Volt::route('reset-password/{token}', 'auth.reset-password')
        ->name('password.reset');

    Volt::route('two-factor-challenge', 'auth.two-factor-challenge')
        ->name('two-factor.challenge');
});



Route::middleware('auth')->group(function () {
    Volt::route('verify-email', 'auth.verify-email')
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Volt::route('confirm-password', 'auth.confirm-password')
        ->name('password.confirm');
});

Route::post('logout', App\Livewire\Actions\Logout::class)
    ->name('logout');
