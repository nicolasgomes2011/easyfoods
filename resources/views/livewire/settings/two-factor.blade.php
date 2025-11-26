<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public bool $showingQrCode = false;
    public bool $showingRecoveryCodes = false;
    public array $recoveryCodes = [];
    public string $qrCodeUrl = '';
    public string $secret = '';
    public string $confirmationCode = '';
    
    /**
     * Enable two-factor authentication.
     */
    public function enableTwoFactor(): void
    {
        $user = Auth::user();
        
        // Generate secret
        $this->secret = $user->generateTwoFactorSecret();
        
        // Generate QR code
        $this->qrCodeUrl = $user->getTwoFactorQrCodeUrl();
        
        // Generate recovery codes
        $this->recoveryCodes = $user->generateRecoveryCodes();
        
        // Show QR code and recovery codes
        $this->showingQrCode = true;
        $this->showingRecoveryCodes = true;
    }
    
    /**
     * Confirm and activate two-factor authentication.
     */
    public function confirmTwoFactor(): void
    {
        $this->validate([
            'confirmationCode' => 'required|string',
        ]);
        
        $user = Auth::user();
        
        if (!$user->verifyTwoFactorCode($this->confirmationCode)) {
            throw ValidationException::withMessages([
                'confirmationCode' => __('The provided code is invalid.'),
            ]);
        }
        
        // Enable 2FA
        $user->enableTwoFactorAuthentication();
        
        // Reset state
        $this->reset(['showingQrCode', 'confirmationCode']);
        
        session()->flash('status', 'Two-factor authentication has been enabled.');
    }
    
    /**
     * Disable two-factor authentication.
     */
    public function disableTwoFactor(): void
    {
        $user = Auth::user();
        $user->disableTwoFactorAuthentication();
        
        $this->reset(['showingQrCode', 'showingRecoveryCodes', 'recoveryCodes', 'qrCodeUrl', 'secret']);
        
        session()->flash('status', 'Two-factor authentication has been disabled.');
    }
    
    /**
     * Show recovery codes.
     */
    public function showRecoveryCodes(): void
    {
        $user = Auth::user();
        $this->recoveryCodes = $user->getRecoveryCodes() ?? [];
        $this->showingRecoveryCodes = true;
    }
    
    /**
     * Regenerate recovery codes.
     */
    public function regenerateRecoveryCodes(): void
    {
        $user = Auth::user();
        $this->recoveryCodes = $user->generateRecoveryCodes();
        $this->showingRecoveryCodes = true;
        
        session()->flash('status', 'New recovery codes have been generated.');
    }
}; ?>

<div>
    <flux:heading size="xl">Two-Factor Authentication</flux:heading>
    <flux:subheading class="mb-6">Add additional security to your account using two-factor authentication.</flux:subheading>

    @if (session('status'))
        <div class="mb-4 p-4 rounded-lg bg-green-50 dark:bg-green-900/20 text-green-800 dark:text-green-200">
            {{ session('status') }}
        </div>
    @endif

    @if (!Auth::user()->two_factor_enabled)
        <div class="space-y-4">
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                When two-factor authentication is enabled, you will be prompted for a secure, random token during authentication. You may retrieve this token from your phone's authenticator application.
            </p>

            @if (!$showingQrCode)
                <flux:button wire:click="enableTwoFactor" variant="primary">
                    Enable Two-Factor Authentication
                </flux:button>
            @else
                <div class="space-y-4">
                    <div class="p-6 bg-white dark:bg-zinc-800 rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <p class="text-sm font-medium mb-4">
                            Scan the following QR code with your authenticator app:
                        </p>
                        
                        <div class="flex justify-center mb-4">
                            <div class="bg-white p-4 rounded-lg">
                                {!! QrCode::size(200)->generate($qrCodeUrl) !!}
                            </div>
                        </div>
                        
                        <p class="text-xs text-zinc-600 dark:text-zinc-400 mb-2">
                            Or enter this secret key manually:
                        </p>
                        <code class="block p-2 bg-zinc-100 dark:bg-zinc-900 rounded text-sm font-mono">
                            {{ $secret }}
                        </code>
                    </div>

                    @if ($showingRecoveryCodes)
                        <div class="p-6 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
                            <p class="text-sm font-medium mb-2 text-amber-800 dark:text-amber-200">
                                Store these recovery codes in a secure location. They can be used to recover access to your account if your two-factor authentication device is lost.
                            </p>
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                @foreach ($recoveryCodes as $code)
                                    <code class="block p-2 bg-white dark:bg-zinc-800 rounded text-sm font-mono">
                                        {{ $code }}
                                    </code>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="space-y-2">
                        <flux:input 
                            wire:model="confirmationCode" 
                            label="Confirmation Code" 
                            type="text" 
                            placeholder="123456"
                            description="To finish enabling two-factor authentication, enter the code from your authenticator app."
                        />
                        
                        <div class="flex gap-2">
                            <flux:button wire:click="confirmTwoFactor" variant="primary">
                                Confirm & Enable
                            </flux:button>
                            <flux:button wire:click="disableTwoFactor" variant="ghost">
                                Cancel
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @else
        <div class="space-y-4">
            <div class="p-4 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800">
                <p class="text-sm font-medium text-green-800 dark:text-green-200">
                    Two-factor authentication is enabled.
                </p>
            </div>

            <div class="flex gap-2">
                <flux:button wire:click="showRecoveryCodes" variant="outline">
                    Show Recovery Codes
                </flux:button>
                <flux:button wire:click="regenerateRecoveryCodes" variant="outline">
                    Regenerate Recovery Codes
                </flux:button>
                <flux:button wire:click="disableTwoFactor" variant="danger">
                    Disable Two-Factor Authentication
                </flux:button>
            </div>

            @if ($showingRecoveryCodes)
                <div class="p-6 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
                    <p class="text-sm font-medium mb-2 text-amber-800 dark:text-amber-200">
                        Store these recovery codes in a secure location. They can be used to recover access to your account if your two-factor authentication device is lost.
                    </p>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        @foreach ($recoveryCodes as $code)
                            <code class="block p-2 bg-white dark:bg-zinc-800 rounded text-sm font-mono">
                                {{ $code }}
                            </code>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
