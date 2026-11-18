<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.guest')]
class LoginForm extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        // The callback only runs once the password matched, so "account
        // disabled" is never shown for a wrong password.
        $inactive = false;
        $credentials = ['email' => $this->email, 'password' => $this->password];

        if (! Auth::attemptWhen($credentials, function (User $user) use (&$inactive) {
            $inactive = ! $user->isActive();

            return ! $inactive;
        }, $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => $inactive ? 'Sua conta foi desativada.' : __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        $user = Auth::user();

        if ($user && $user->two_factor_enabled) {
            Auth::logout();

            Session::put('two_factor_login_id', $user->id);
            Session::put('two_factor_remember', $this->remember);

            $this->redirect(route('two-factor.challenge'), navigate: true);
            return;
        }

        Session::regenerate();

        $this->redirectIntended(default: route($user->homeRoute(), absolute: false), navigate: true);
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email) . '|' . request()->ip());
    }

    public function render()
    {
        return view('livewire.auth.login-form');
    }
}
