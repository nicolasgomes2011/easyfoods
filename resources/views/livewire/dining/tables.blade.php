<?php
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Salão — Mesas</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Controle de ocupação em tempo real</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.dining.queue') }}" wire:navigate
               class="text-sm text-zinc-300 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 rounded-lg px-3 py-1.5 transition">
                Fila de espera
            </a>
        </div>
    </div>

    <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-5 py-16 text-center">
        <div class="size-12 rounded-xl bg-zinc-800 flex items-center justify-center mx-auto mb-4">
            <svg class="size-6 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M3 14h18M10 3v18M14 3v18"/>
            </svg>
        </div>
        <p class="text-white font-medium mb-1">Módulo de mesas</p>
        <p class="text-zinc-500 text-sm max-w-sm mx-auto">
            O controle de ocupação de mesas está previsto para uma próxima fase do sistema.
        </p>
    </div>
</div>
