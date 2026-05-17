<?php
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Fila de Espera</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Clientes aguardando mesa disponível</p>
        </div>
        <a href="{{ route('admin.dining.tables') }}" wire:navigate
           class="text-sm text-zinc-300 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 rounded-lg px-3 py-1.5 transition">
            Ver mesas
        </a>
    </div>

    <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-5 py-16 text-center">
        <div class="size-12 rounded-xl bg-zinc-800 flex items-center justify-center mx-auto mb-4">
            <svg class="size-6 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </div>
        <p class="text-white font-medium mb-1">Módulo de fila de espera</p>
        <p class="text-zinc-500 text-sm max-w-sm mx-auto">
            O gerenciamento de fila de espera para mesas está previsto para uma próxima fase do sistema.
        </p>
    </div>
</div>
