<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    #[Validate('required|string')]
    public string $code = '';
    
    public bool $useRecoveryCode = false;

    /**
     * Verify the two-factor authentication code.
     */
    public function verify(): void
    {
        $this->validate();
        
        $this->ensureIsNotRateLimited();
        
        $userId = Session::get('two_factor_login_id');
        
        if (!$userId) {
            $this->redirect(route('login'), navigate: true);
            return;
        }
        
        $user = User::find($userId);
        
        if (!$user || !$user->two_factor_enabled) {
            Session::forget(['two_factor_login_id', 'two_factor_remember']);
            $this->redirect(route('login'), navigate: true);
            return;
        }
        
        $valid = false;
        
        if ($this->useRecoveryCode) {
            $valid = $user->verifyRecoveryCode($this->code);
        } else {
            $valid = $user->verifyTwoFactorCode($this->code);
        }
        
        if (!$valid) {
            RateLimiter::hit($this->throttleKey());
            
            throw ValidationException::withMessages([
                'code' => __('The provided code is invalid.'),
            ]);
        }
        
        RateLimiter::clear($this->throttleKey());
        
        // Log the user in
        Auth::login($user, Session::get('two_factor_remember', false));
        
        Session::forget(['two_factor_login_id', 'two_factor_remember']);
        Session::regenerate();
        
        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
    
    /**
     * Toggle between using code and recovery code.
     */
    public function toggleRecoveryCode(): void
    {
        $this->useRecoveryCode = !$this->useRecoveryCode;
        $this->code = '';
    }
    
    /**
     * Ensure the request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }
        
        $seconds = RateLimiter::availableIn($this->throttleKey());
        
        throw ValidationException::withMessages([
            'code' => __('Too many attempts. Please try again in :seconds seconds.', [
                'seconds' => $seconds,
            ]),
        ]);
    }
    
    /**
     * Get the rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        $userId = Session::get('two_factor_login_id', 'unknown');
        return Str::transliterate('two-factor-'.$userId.'|'.request()->ip());
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header 
        title="Two-Factor Authentication" 
        :description="$useRecoveryCode ? 'Enter one of your recovery codes' : 'Enter the code from your authenticator app'" 
    />

    <form wire:submit="verify" class="flex flex-col gap-6">
        <!-- Two-Factor Code -->
        <flux:input 
            wire:model="code" 
            :label="$useRecoveryCode ? __('Recovery Code') : __('Authentication Code')" 
            type="text" 
            name="code" 
            required 
            autofocus 
            :placeholder="$useRecoveryCode ? 'XXXXXXXXXXXX' : '123456'"
            inputmode="numeric"
        />

        <div class="flex items-center justify-end">
            <flux:button variant="primary" type="submit" class="w-full">{{ __('Verify') }}</flux:button>
        </div>
    </form>
    
    <div class="text-center">
        <button 
            type="button" 
            wire:click="toggleRecoveryCode"
            class="text-sm text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-zinc-100 underline"
        >
            {{ $useRecoveryCode ? __('Use authentication code') : __('Use a recovery code') }}
        </button>
    </div>

    <div class="space-x-1 text-center text-sm text-zinc-600 dark:text-zinc-400">
        <x-text-link href="{{ route('login') }}">Back to login</x-text-link>
    </div>
</div>
