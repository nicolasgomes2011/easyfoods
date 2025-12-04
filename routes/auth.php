<?php

use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Livewire\Auth\LoginForm;
//Route::middleware('guest')->group(function () {
//    Volt::route('login', 'auth.login')
//        ->name('login');
//
//    Volt::route('register', 'auth.register')
//        ->name('register');
//
//    Volt::route('forgot-password', 'auth.forgot-password')
//        ->name('password.request');
//
//    Volt::route('reset-password/{token}', 'auth.reset-password')
//        ->name('password.reset');
//
//    Volt::route('two-factor-challenge', 'auth.two-factor-challenge')
//        ->name('two-factor.challenge');
//
//});

Route::get('/login', LoginForm::class)->name('login')->middleware('guest');

// Mostrar formulário "esqueci a senha"
Route::get('/forgot-password', function () {
    return view('auth.forgot-password'); // você cria essa view
})->middleware('guest')->name('password.request');

// Enviar email com link de reset
Route::post('/forgot-password', function (Request $request) {
    $request->validate(['email' => 'required|email']);

    $status = Password::sendResetLink($request->only('email'));

    return $status === Password::RESET_LINK_SENT
        ? back()->with('status', __($status))
        : back()->withErrors(['email' => __($status)]);
})->middleware('guest')->name('password.email');

// Mostrar formulário para inserir nova senha (token recebido por email)
Route::get('/reset-password/{token}', function ($token) {
    return view('auth.reset-password', ['token' => $token]); // você cria essa view
})->middleware('guest')->name('password.reset');

// Submeter nova senha
Route::post('/reset-password', function (Request $request) {
    $request->validate([
        'token' => 'required',
        'email' => 'required|email',
        'password' => 'required|confirmed|min:8',
    ]);

    $status = Password::reset(
        $request->only('email', 'password', 'password_confirmation', 'token'),
        function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
            ])->save();

            // opcional: $user->setRememberToken(Str::random(60));
        }
    );

    return $status === Password::PASSWORD_RESET
        ? redirect()->route('login')->with('status', __($status))
        : back()->withErrors(['email' => [__($status)]]);
})->middleware('guest')->name('password.update');



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
