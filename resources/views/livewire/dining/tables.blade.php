<?php
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    // Fase MVP: controle de mesas — ocupação, capacidade, fila de espera
}; ?>

<div>
    {{-- Header --}}
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
            <button class="text-sm text-white bg-orange-500 hover:bg-orange-600 rounded-lg px-3 py-1.5 transition">
                + Registrar ocupação
            </button>
        </div>
    </div>

    {{-- Summary --}}
    <div class="grid grid-cols-4 gap-3 mb-6">
        @foreach([
            ['label' => 'Total de mesas', 'value' => '—', 'color' => 'zinc'],
            ['label' => 'Ocupadas',        'value' => '—', 'color' => 'orange'],
            ['label' => 'Disponíveis',     'value' => '—', 'color' => 'green'],
            ['label' => 'Reservadas',      'value' => '—', 'color' => 'blue'],
        ] as $s)
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3 text-center">
            <p class="text-2xl font-bold text-white tabular-nums">{{ $s['value'] }}</p>
            <p class="text-xs text-zinc-400 mt-1">{{ $s['label'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Table map (grid placeholder) --}}
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-5 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-white">Mapa do salão</h2>
            <div class="flex items-center gap-4 text-xs text-zinc-400">
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded bg-zinc-700"></span> Disponível</span>
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded bg-orange-500/70"></span> Ocupada</span>
                <span class="flex items-center gap-1.5"><span class="size-2.5 rounded bg-blue-500/70"></span> Reservada</span>
            </div>
        </div>
        <div class="grid grid-cols-4 sm:grid-cols-6 lg:grid-cols-8 gap-3">
            @foreach(range(1, 16) as $table)
            @php
                $state = match(true) {
                    in_array($table, [2, 5, 7, 11, 13]) => 'occupied',
                    in_array($table, [9])                => 'reserved',
                    default                              => 'free',
                };
                $style = match($state) {
                    'occupied' => 'bg-orange-500/20 border-orange-500/40 text-orange-300',
                    'reserved' => 'bg-blue-500/20 border-blue-500/40 text-blue-300',
                    default    => 'bg-zinc-800 border-zinc-700 text-zinc-400 hover:border-zinc-500',
                };
            @endphp
            <button class="aspect-square rounded-xl border {{ $style }} flex flex-col items-center justify-center gap-1 transition cursor-pointer">
                <span class="text-sm font-bold">{{ $table }}</span>
                <span class="text-xs opacity-60">4 lug.</span>
            </button>
            @endforeach
        </div>
    </div>

    {{-- Active tables list --}}
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl">
        <div class="px-5 py-4 border-b border-zinc-800">
            <h2 class="text-sm font-semibold text-white">Mesas com pedidos ativos</h2>
        </div>
        <div class="divide-y divide-zinc-800/70">
            @foreach([
                ['table' => 'Mesa 2',  'guests' => 3, 'since' => '45 min', 'order' => '#0004', 'status' => 'Em preparo',  'value' => 'R$ 123,40'],
                ['table' => 'Mesa 5',  'guests' => 2, 'since' => '22 min', 'order' => '#0007', 'status' => 'Confirmado',  'value' => 'R$ 64,00'],
                ['table' => 'Mesa 7',  'guests' => 5, 'since' => '67 min', 'order' => '#0001', 'status' => 'Pronto',      'value' => 'R$ 218,50'],
                ['table' => 'Mesa 11', 'guests' => 2, 'since' => '10 min', 'order' => '#0009', 'status' => 'Aguardando',  'value' => 'R$ 42,00'],
                ['table' => 'Mesa 13', 'guests' => 4, 'since' => '35 min', 'order' => '#0003', 'status' => 'Em preparo',  'value' => 'R$ 156,80'],
            ] as $row)
            <div class="flex items-center justify-between px-5 py-3.5 hover:bg-zinc-800/40 transition">
                <div class="flex items-center gap-4">
                    <span class="text-sm font-medium text-white w-20">{{ $row['table'] }}</span>
                    <span class="text-xs text-zinc-500">{{ $row['guests'] }} pessoas · {{ $row['since'] }}</span>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-xs font-mono text-zinc-400">{{ $row['order'] }}</span>
                    <span class="text-sm font-medium text-zinc-200 tabular-nums">{{ $row['value'] }}</span>
                    <span class="text-xs font-medium text-orange-300 bg-orange-500/15 rounded-full px-2 py-0.5">{{ $row['status'] }}</span>
                </div>
            </div>
            @endforeach
        </div>
        <div class="px-5 py-3 border-t border-zinc-800">
            <p class="text-xs text-zinc-600 italic">Dados de exemplo. Integrar com o modelo <code class="text-zinc-500">Order</code> filtrado por tipo dine-in.</p>
        </div>
    </div>
</div>
