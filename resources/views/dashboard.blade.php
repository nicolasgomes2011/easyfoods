<x-layouts.app>
    {{-- ── Page header ─────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-8">
        <div>
            <h1 class="text-xl font-bold text-white">Dashboard Operacional</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Visão em tempo real da operação do restaurante</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 text-xs text-zinc-400 bg-zinc-800 border border-zinc-700 rounded-lg px-3 py-1.5">
                <span class="size-1.5 rounded-full bg-green-400 animate-pulse"></span>
                Ao vivo
            </span>
            <span class="text-xs text-zinc-500 bg-zinc-800 border border-zinc-700 rounded-lg px-3 py-1.5">
                {{ now()->format('d/m/Y H:i') }}
            </span>
        </div>
    </div>

    {{-- ── KPI Cards ───────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3 mb-8">

        @php
        $kpis = [
            ['label' => 'Pedidos abertos',   'value' => '—',    'sub' => 'aguardando confirmação', 'color' => 'yellow'],
            ['label' => 'Em preparo',         'value' => '—',    'sub' => 'na cozinha agora',       'color' => 'orange'],
            ['label' => 'Prontos',            'value' => '—',    'sub' => 'aguardando retirada',    'color' => 'green'],
            ['label' => 'Mesas ocupadas',     'value' => '—',    'sub' => 'de — disponíveis',       'color' => 'blue'],
            ['label' => 'Tempo médio',        'value' => '—',    'sub' => 'minutos por pedido',     'color' => 'purple'],
            ['label' => 'Faturamento hoje',   'value' => 'R$ —', 'sub' => 'em — pedidos',           'color' => 'zinc'],
        ];

        $colorMap = [
            'yellow' => ['dot' => 'bg-yellow-400', 'ring' => 'ring-yellow-400/20 bg-yellow-400/10 text-yellow-400'],
            'orange' => ['dot' => 'bg-orange-400', 'ring' => 'ring-orange-400/20 bg-orange-400/10 text-orange-400'],
            'green'  => ['dot' => 'bg-green-400',  'ring' => 'ring-green-400/20 bg-green-400/10 text-green-400'],
            'blue'   => ['dot' => 'bg-blue-400',   'ring' => 'ring-blue-400/20 bg-blue-400/10 text-blue-400'],
            'purple' => ['dot' => 'bg-purple-400', 'ring' => 'ring-purple-400/20 bg-purple-400/10 text-purple-400'],
            'zinc'   => ['dot' => 'bg-zinc-400',   'ring' => 'ring-zinc-600 bg-zinc-800 text-zinc-300'],
        ];
        @endphp

        @foreach($kpis as $kpi)
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-4 flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <p class="text-xs font-medium text-zinc-400 leading-tight">{{ $kpi['label'] }}</p>
                <span class="size-2 rounded-full {{ $colorMap[$kpi['color']]['dot'] }}"></span>
            </div>
            <div>
                <p class="text-2xl font-bold text-white tabular-nums">{{ $kpi['value'] }}</p>
                <p class="text-xs text-zinc-500 mt-0.5">{{ $kpi['sub'] }}</p>
            </div>
        </div>
        @endforeach

    </div>

    {{-- ── Middle section ──────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

        {{-- Pedidos recentes (2/3) --}}
        <div class="lg:col-span-2 bg-zinc-900 border border-zinc-800 rounded-xl">
            <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-800">
                <h2 class="text-sm font-semibold text-white">Pedidos recentes</h2>
                <a href="{{ route('admin.orders.index') }}" class="text-xs text-orange-400 hover:text-orange-300 transition" wire:navigate>
                    Ver todos →
                </a>
            </div>
            <div class="divide-y divide-zinc-800/70">
                @foreach([
                    ['ref' => '#0001', 'origin' => 'Mesa 4',    'time' => '3 min',  'value' => 'R$ 87,90',  'status' => 'Aguardando', 'class' => 'bg-yellow-500/15 text-yellow-400'],
                    ['ref' => '#0002', 'origin' => 'Delivery',  'time' => '8 min',  'value' => 'R$ 54,50',  'status' => 'Em preparo',  'class' => 'bg-orange-500/15 text-orange-400'],
                    ['ref' => '#0003', 'origin' => 'Balcão',    'time' => '12 min', 'value' => 'R$ 32,00',  'status' => 'Confirmado',  'class' => 'bg-blue-500/15 text-blue-400'],
                    ['ref' => '#0004', 'origin' => 'Mesa 2',    'time' => '19 min', 'value' => 'R$ 123,40', 'status' => 'Em preparo',  'class' => 'bg-orange-500/15 text-orange-400'],
                    ['ref' => '#0005', 'origin' => 'Delivery',  'time' => '25 min', 'value' => 'R$ 66,00',  'status' => 'Pronto',      'class' => 'bg-green-500/15 text-green-400'],
                ] as $order)
                <div class="flex items-center justify-between px-5 py-3.5 hover:bg-zinc-800/40 transition">
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-mono text-zinc-500">{{ $order['ref'] }}</span>
                        <div>
                            <p class="text-sm font-medium text-white">{{ $order['origin'] }}</p>
                            <p class="text-xs text-zinc-500">há {{ $order['time'] }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium text-zinc-200 tabular-nums">{{ $order['value'] }}</span>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $order['class'] }}">
                            {{ $order['status'] }}
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="px-5 py-3 border-t border-zinc-800">
                <p class="text-xs text-zinc-600 italic">Dados de exemplo — conecte ao modelo Order para exibir pedidos reais.</p>
            </div>
        </div>

        {{-- Fila da cozinha (1/3) --}}
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl">
            <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-800">
                <h2 class="text-sm font-semibold text-white">Fila da cozinha</h2>
                <a href="{{ route('admin.kitchen.index') }}" class="text-xs text-orange-400 hover:text-orange-300 transition" wire:navigate>
                    Abrir fila →
                </a>
            </div>
            <div class="px-5 py-4 space-y-1">
                @foreach([
                    ['item' => 'X-Burguer duplo',  'qty' => 2, 'time' => '8 min',  'urgent' => true],
                    ['item' => 'Frango grelhado',  'qty' => 1, 'time' => '12 min', 'urgent' => false],
                    ['item' => 'Salada caesar',    'qty' => 3, 'time' => '5 min',  'urgent' => false],
                    ['item' => 'Batata frita G',   'qty' => 4, 'time' => '15 min', 'urgent' => true],
                    ['item' => 'Suco de laranja',  'qty' => 2, 'time' => '3 min',  'urgent' => false],
                ] as $item)
                <div class="flex items-center justify-between py-2.5 border-b border-zinc-800/60 last:border-0">
                    <div class="flex items-center gap-2.5">
                        <span class="size-1.5 rounded-full shrink-0 {{ $item['urgent'] ? 'bg-red-400' : 'bg-zinc-600' }}"></span>
                        <div>
                            <p class="text-sm text-white font-medium">{{ $item['item'] }}</p>
                            <p class="text-xs text-zinc-500">×{{ $item['qty'] }}</p>
                        </div>
                    </div>
                    <span class="text-xs text-zinc-400 tabular-nums {{ $item['urgent'] ? 'text-red-400' : '' }}">
                        {{ $item['time'] }}
                    </span>
                </div>
                @endforeach
            </div>
            <div class="px-5 pb-4">
                <p class="text-xs text-zinc-600 italic">Dados de exemplo.</p>
            </div>
        </div>

    </div>

    {{-- ── Bottom section ───────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Mais pedidos hoje --}}
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl">
            <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-800">
                <h2 class="text-sm font-semibold text-white">Itens mais pedidos hoje</h2>
                <a href="{{ route('admin.reports.index') }}" class="text-xs text-orange-400 hover:text-orange-300 transition" wire:navigate>
                    Ver relatório →
                </a>
            </div>
            <div class="px-5 py-4 space-y-3.5">
                @foreach([
                    ['name' => 'X-Burguer Clássico', 'qty' => 42, 'pct' => 100],
                    ['name' => 'Frango Grelhado',     'qty' => 38, 'pct' => 90],
                    ['name' => 'Batata Frita G',      'qty' => 31, 'pct' => 74],
                    ['name' => 'Refrigerante 500ml',  'qty' => 29, 'pct' => 69],
                    ['name' => 'Salada Caesar',       'qty' => 18, 'pct' => 43],
                ] as $item)
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-white">{{ $item['name'] }}</span>
                        <span class="text-xs text-zinc-400 tabular-nums">{{ $item['qty'] }}×</span>
                    </div>
                    <div class="h-1.5 rounded-full bg-zinc-800">
                        <div class="h-1.5 rounded-full bg-orange-500 transition-all" style="width: {{ $item['pct'] }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Alertas operacionais --}}
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl">
            <div class="px-5 py-4 border-b border-zinc-800">
                <h2 class="text-sm font-semibold text-white">Alertas operacionais</h2>
            </div>
            <div class="px-5 py-4 space-y-3">
                <div class="flex items-start gap-3 p-3 rounded-lg bg-red-500/10 border border-red-500/20">
                    <svg class="size-4 text-red-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-red-300">Pedido #0004 atrasado</p>
                        <p class="text-xs text-red-400/70 mt-0.5">Mais de 30 min sem atualização de status.</p>
                    </div>
                </div>
                <div class="flex items-start gap-3 p-3 rounded-lg bg-yellow-500/10 border border-yellow-500/20">
                    <svg class="size-4 text-yellow-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-yellow-300">Cozinha com alta carga</p>
                        <p class="text-xs text-yellow-400/70 mt-0.5">8 itens em preparo simultâneo.</p>
                    </div>
                </div>
                <div class="flex items-start gap-3 p-3 rounded-lg bg-zinc-800 border border-zinc-700">
                    <svg class="size-4 text-zinc-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-zinc-300">Módulo de alertas</p>
                        <p class="text-xs text-zinc-500 mt-0.5">Alertas reais aparecerão aqui ao integrar os dados operacionais.</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-layouts.app>
