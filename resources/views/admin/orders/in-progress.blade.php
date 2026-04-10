<x-layouts.app>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Pedidos em andamento</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Pedidos confirmados e em preparo neste momento</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.orders.index') }}" wire:navigate
               class="text-sm text-zinc-300 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 rounded-lg px-3 py-1.5 transition">
                Todos os pedidos
            </a>
            <a href="{{ route('admin.kitchen.index') }}" wire:navigate
               class="text-sm text-white bg-orange-500 hover:bg-orange-600 rounded-lg px-3 py-1.5 transition">
                Abrir cozinha
            </a>
        </div>
    </div>

    {{-- Status tabs --}}
    <div class="flex gap-2 mb-5">
        @foreach([
            ['label' => 'Confirmados', 'count' => '—', 'active' => false],
            ['label' => 'Em preparo',  'count' => '—', 'active' => true],
            ['label' => 'Prontos',     'count' => '—', 'active' => false],
        ] as $tab)
        <button class="text-sm px-3 py-1.5 rounded-lg border transition {{ $tab['active'] ? 'bg-orange-500/20 border-orange-500/40 text-orange-300' : 'bg-zinc-900 border-zinc-700 text-zinc-400 hover:border-zinc-500' }}">
            {{ $tab['label'] }}
            <span class="ml-1 text-xs opacity-70">{{ $tab['count'] }}</span>
        </button>
        @endforeach
    </div>

    <div class="bg-zinc-900 border border-zinc-800 rounded-xl">
        <div class="px-5 py-3 border-b border-zinc-800">
            <p class="text-xs text-zinc-600 italic">
                Implementar filtrando <code class="text-zinc-500">Order::active()</code> pelos status
                <code class="text-zinc-500">confirmed</code> e <code class="text-zinc-500">in_preparation</code>.
                Considere polling via <code class="text-zinc-500">wire:poll</code> a cada 30s.
            </p>
        </div>
        <div class="px-5 py-12 text-center">
            <p class="text-zinc-500 text-sm">Nenhum pedido em andamento no momento.</p>
            <p class="text-zinc-600 text-xs mt-1">Este painel mostrará pedidos ativos em tempo real.</p>
        </div>
    </div>
</x-layouts.app>
