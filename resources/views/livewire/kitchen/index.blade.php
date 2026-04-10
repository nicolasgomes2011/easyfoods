<?php
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    // Fase MVP: fila de preparo será carregada via polling dos pedidos com status in_preparation
}; ?>

<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Cozinha</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Fila de preparo em tempo real</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 text-xs text-orange-400 bg-orange-400/10 border border-orange-400/20 rounded-lg px-3 py-1.5">
                <span class="size-1.5 rounded-full bg-orange-400 animate-pulse"></span>
                Em andamento
            </span>
        </div>
    </div>

    {{-- Status summary --}}
    <div class="grid grid-cols-3 gap-3 mb-6">
        @foreach([
            ['label' => 'Aguardando preparo', 'value' => '—', 'color' => 'yellow'],
            ['label' => 'Em preparo',         'value' => '—', 'color' => 'orange'],
            ['label' => 'Prontos',            'value' => '—', 'color' => 'green'],
        ] as $stat)
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3 text-center">
            <p class="text-2xl font-bold text-white tabular-nums">{{ $stat['value'] }}</p>
            <p class="text-xs text-zinc-400 mt-1">{{ $stat['label'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Queue cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach([
            ['order' => '#0002', 'origin' => 'Delivery',  'time' => '18 min', 'items' => ['X-Burguer duplo ×2', 'Batata Frita G ×2', 'Refrigerante ×2'], 'status' => 'in_preparation', 'urgent' => true],
            ['order' => '#0004', 'origin' => 'Mesa 2',    'time' => '12 min', 'items' => ['Frango grelhado ×1', 'Salada Caesar ×2', 'Suco laranja ×2'], 'status' => 'confirmed',      'urgent' => false],
            ['order' => '#0006', 'origin' => 'Balcão',    'time' => '5 min',  'items' => ['Prato do dia ×1', 'Água mineral ×1'],                        'status' => 'confirmed',      'urgent' => false],
        ] as $card)
        <div class="bg-zinc-900 border {{ $card['urgent'] ? 'border-orange-500/50' : 'border-zinc-800' }} rounded-xl overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b {{ $card['urgent'] ? 'border-orange-500/30 bg-orange-500/5' : 'border-zinc-800' }}">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-bold font-mono {{ $card['urgent'] ? 'text-orange-400' : 'text-white' }}">
                        {{ $card['order'] }}
                    </span>
                    <span class="text-xs text-zinc-400">{{ $card['origin'] }}</span>
                </div>
                <div class="flex items-center gap-1.5 text-xs text-zinc-400">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ $card['time'] }}
                </div>
            </div>
            <div class="px-4 py-3 space-y-1.5">
                @foreach($card['items'] as $item)
                <div class="flex items-center gap-2">
                    <span class="size-1.5 rounded-full bg-zinc-600 shrink-0"></span>
                    <span class="text-sm text-zinc-300">{{ $item }}</span>
                </div>
                @endforeach
            </div>
            <div class="px-4 py-3 border-t border-zinc-800">
                <button class="w-full text-center text-xs font-medium text-orange-400 hover:text-orange-300 transition py-1 rounded-lg hover:bg-orange-400/10">
                    Marcar como pronto →
                </button>
            </div>
        </div>
        @endforeach
    </div>

    <div class="mt-6 px-4 py-3 bg-zinc-900 border border-zinc-800 rounded-xl">
        <p class="text-xs text-zinc-500 italic text-center">
            Dados de exemplo. A fila real será carregada via polling dos pedidos com status <code class="text-zinc-400">in_preparation</code> e <code class="text-zinc-400">confirmed</code>.
        </p>
    </div>
</div>
