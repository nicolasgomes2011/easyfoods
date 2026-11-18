<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::sendResetLink($this->only('email'));

        session()->flash('status', 'Se a conta existir, enviaremos um link de redefinição.');
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Esqueci minha senha" description="Informe seu e-mail para receber o link de redefinição" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="flex flex-col gap-6">
        <!-- Email Address -->
        <div class="grid gap-2">
            <flux:input wire:model="email" label="E-mail" type="email" name="email" required autofocus autocomplete="email" placeholder="seu@email.com" />
        </div>

        <flux:button variant="primary" type="submit" class="w-full">Enviar link de redefinição</flux:button>
    </form>

    <div class="space-x-1 text-center text-sm text-zinc-400">
        Ou volte para o
        <x-text-link href="{{ route('login') }}">login</x-text-link>
    </div>
</div>
