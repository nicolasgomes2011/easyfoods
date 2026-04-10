<?php
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    // Fase MVP: fila de espera para alocação de mesa
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Fila de Espera</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Clientes aguardando mesa disponível</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.dining.tables') }}" wire:navigate
               class="text-sm text-zinc-300 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 rounded-lg px-3 py-1.5 transition">
                Ver mesas
            </a>
            <button class="text-sm text-white bg-orange-500 hover:bg-orange-600 rounded-lg px-3 py-1.5 transition">
                + Adicionar à fila
            </button>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-3 mb-6">
        @foreach([
            ['label' => 'Na fila agora', 'value' => '—'],
            ['label' => 'Espera média',  'value' => '— min'],
            ['label' => 'Atendidos hoje','value' => '—'],
        ] as $s)
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3 text-center">
            <p class="text-2xl font-bold text-white tabular-nums">{{ $s['value'] }}</p>
            <p class="text-xs text-zinc-400 mt-1">{{ $s['label'] }}</p>
        </div>
        @endforeach
    </div>

    <div class="bg-zinc-900 border border-zinc-800 rounded-xl">
        <div class="px-5 py-4 border-b border-zinc-800">
            <h2 class="text-sm font-semibold text-white">Aguardando</h2>
        </div>
        <div class="divide-y divide-zinc-800/70">
            @foreach([
                ['pos' => 1, 'name' => 'Família Silva',  'party' => 4, 'wait' => '12 min', 'priority' => false],
                ['pos' => 2, 'name' => 'João M.',        'party' => 2, 'wait' => '8 min',  'priority' => false],
                ['pos' => 3, 'name' => 'Ana P. (+id.)',  'party' => 3, 'wait' => '5 min',  'priority' => true],
            ] as $entry)
            <div class="flex items-center justify-between px-5 py-4 hover:bg-zinc-800/40 transition">
                <div class="flex items-center gap-4">
                    <span class="size-8 rounded-full bg-zinc-800 border border-zinc-700 flex items-center justify-center text-sm font-bold text-zinc-300">
                        {{ $entry['pos'] }}
                    </span>
                    <div>
                        <p class="text-sm font-medium text-white flex items-center gap-2">
                            {{ $entry['name'] }}
                            @if($entry['priority'])
                            <span class="text-xs text-blue-400 bg-blue-400/10 rounded-full px-2 py-0.5">Prioritário</span>
                            @endif
                        </p>
                        <p class="text-xs text-zinc-500">{{ $entry['party'] }} pessoas · aguardando {{ $entry['wait'] }}</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button class="text-xs text-green-400 bg-green-400/10 hover:bg-green-400/20 rounded-lg px-3 py-1.5 transition">Alocar mesa</button>
                    <button class="text-xs text-zinc-400 hover:text-red-400 bg-zinc-800 rounded-lg px-3 py-1.5 transition">Remover</button>
                </div>
            </div>
            @endforeach
        </div>
        <div class="px-5 py-3 border-t border-zinc-800">
            <p class="text-xs text-zinc-600 italic">Módulo de fila de espera — a ser implementado com modelo dedicado (WaitingList).</p>
        </div>
    </div>
</div>
