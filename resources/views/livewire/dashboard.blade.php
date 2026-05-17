<div>
    {{-- ── Page header ─────────────────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-8">
        <div>
            <h1 class="text-xl font-bold text-white">Dashboard Operacional</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Visão em tempo real da operação do restaurante</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 text-xs text-zinc-400 bg-zinc-800 border border-zinc-700 rounded-lg px-3 py-1.5">
                <span class="size-1.5 rounded-full bg-green-400 animate-pulse"></span>
                Ao vivo · atualiza a cada 30s
            </span>
            <span class="text-xs text-zinc-500 bg-zinc-800 border border-zinc-700 rounded-lg px-3 py-1.5">
                {{ now()->format('d/m/Y H:i') }}
            </span>
        </div>
    </div>

    {{-- ── KPI Cards ───────────────────────────────────────────────────── --}}
    @php
    $kpis = [
        [
            'label' => 'Pedidos abertos',
            'value' => $openCount,
            'sub'   => 'aguardando confirmação',
            'dot'   => 'bg-yellow-400',
        ],
        [
            'label' => 'Em preparo',
            'value' => $inPreparationCount,
            'sub'   => 'na cozinha agora',
            'dot'   => 'bg-orange-400',
        ],
        [
            'label' => 'Prontos',
            'value' => $readyCount,
            'sub'   => 'aguardando retirada',
            'dot'   => 'bg-green-400',
        ],
        [
            'label' => 'Pedidos hoje',
            'value' => $todayOrderCount,
            'sub'   => 'excluindo cancelados',
            'dot'   => 'bg-blue-400',
        ],
        [
            'label' => 'Tempo médio',
            'value' => $avgPrepMinutes !== null ? $avgPrepMinutes . ' min' : '—',
            'sub'   => 'do pedido à retirada hoje',
            'dot'   => 'bg-purple-400',
        ],
        [
            'label' => 'Faturamento hoje',
            'value' => 'R$ ' . number_format($todayRevenue, 2, ',', '.'),
            'sub'   => 'em pedidos entregues/concluídos',
            'dot'   => 'bg-zinc-400',
        ],
    ];
    @endphp

    <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3 mb-8">
        @foreach($kpis as $kpi)
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-4 flex flex-col gap-3">
            <div class="flex items-center justify-between">
                <p class="text-xs font-medium text-zinc-400 leading-tight">{{ $kpi['label'] }}</p>
                <span class="size-2 rounded-full {{ $kpi['dot'] }}"></span>
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

            @php
            $statusBadge = [
                'yellow' => 'bg-yellow-500/15 text-yellow-400',
                'blue'   => 'bg-blue-500/15 text-blue-400',
                'orange' => 'bg-orange-500/15 text-orange-400',
                'purple' => 'bg-purple-500/15 text-purple-400',
                'indigo' => 'bg-indigo-500/15 text-indigo-400',
                'teal'   => 'bg-teal-500/15 text-teal-400',
                'green'  => 'bg-green-500/15 text-green-400',
                'red'    => 'bg-red-500/15 text-red-400',
                'gray'   => 'bg-zinc-800 text-zinc-400',
            ];
            @endphp

            @if($recentOrders->isEmpty())
            <div class="px-5 py-12 text-center">
                <p class="text-zinc-500 text-sm">Nenhum pedido encontrado.</p>
            </div>
            @else
            <div class="divide-y divide-zinc-800/70">
                @foreach($recentOrders as $order)
                <div class="flex items-center justify-between px-5 py-3.5 hover:bg-zinc-800/40 transition">
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-mono text-zinc-500">#{{ $order->number }}</span>
                        <div>
                            <p class="text-sm font-medium text-white">
                                {{ $order->delivery_type === \App\Enums\DeliveryType::Delivery ? 'Delivery' : 'Balcão' }}
                            </p>
                            <p class="text-xs text-zinc-500">{{ $order->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium text-zinc-200 tabular-nums">
                            R$ {{ number_format($order->total, 2, ',', '.') }}
                        </span>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $statusBadge[$order->status->color()] ?? 'bg-zinc-800 text-zinc-400' }}">
                            {{ $order->status->label() }}
                        </span>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Fila da cozinha (1/3) --}}
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl">
            <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-800">
                <h2 class="text-sm font-semibold text-white">Fila da cozinha</h2>
                <a href="{{ route('admin.kitchen.index') }}" class="text-xs text-orange-400 hover:text-orange-300 transition" wire:navigate>
                    Abrir fila →
                </a>
            </div>

            @if($kitchenQueue->isEmpty())
            <div class="px-5 py-10 text-center">
                <p class="text-zinc-500 text-sm">Nenhum item em preparo.</p>
            </div>
            @else
            <div class="px-5 py-4 space-y-1">
                @foreach($kitchenQueue as $item)
                @php
                    $minutesAgo = \Carbon\Carbon::parse($item->oldest_at)->diffInMinutes(now());
                    $urgent     = $minutesAgo >= 20;
                @endphp
                <div class="flex items-center justify-between py-2.5 border-b border-zinc-800/60 last:border-0">
                    <div class="flex items-center gap-2.5">
                        <span class="size-1.5 rounded-full shrink-0 {{ $urgent ? 'bg-red-400' : 'bg-zinc-600' }}"></span>
                        <div>
                            <p class="text-sm text-white font-medium">{{ $item->product_name }}</p>
                            <p class="text-xs text-zinc-500">×{{ $item->total_qty }}</p>
                        </div>
                    </div>
                    <span class="text-xs tabular-nums {{ $urgent ? 'text-red-400' : 'text-zinc-400' }}">
                        {{ $minutesAgo }} min
                    </span>
                </div>
                @endforeach
            </div>
            @endif
        </div>

    </div>

    {{-- ── Bottom section ───────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Itens mais pedidos hoje --}}
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl">
            <div class="flex items-center justify-between px-5 py-4 border-b border-zinc-800">
                <h2 class="text-sm font-semibold text-white">Itens mais pedidos hoje</h2>
                <a href="{{ route('admin.reports.index') }}" class="text-xs text-orange-400 hover:text-orange-300 transition" wire:navigate>
                    Ver relatório →
                </a>
            </div>

            @if($topItemsToday->isEmpty())
            <div class="px-5 py-10 text-center">
                <p class="text-zinc-500 text-sm">Nenhum pedido registrado hoje.</p>
            </div>
            @else
            @php $maxQty = $topItemsToday->max('total_qty') ?: 1; @endphp
            <div class="px-5 py-4 space-y-3.5">
                @foreach($topItemsToday as $item)
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-white">{{ $item->product_name }}</span>
                        <span class="text-xs text-zinc-400 tabular-nums">{{ $item->total_qty }}×</span>
                    </div>
                    <div class="h-1.5 rounded-full bg-zinc-800">
                        <div class="h-1.5 rounded-full bg-orange-500 transition-all" style="width: {{ round(($item->total_qty / $maxQty) * 100) }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Alertas operacionais --}}
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl">
            <div class="px-5 py-4 border-b border-zinc-800">
                <h2 class="text-sm font-semibold text-white">Alertas operacionais</h2>
            </div>
            <div class="px-5 py-4 space-y-3">
                @forelse($alerts as $alert)
                @if($alert['level'] === 'error')
                <div class="flex items-start gap-3 p-3 rounded-lg bg-red-500/10 border border-red-500/20">
                    <svg class="size-4 text-red-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-red-300">{{ $alert['title'] }}</p>
                        <p class="text-xs text-red-400/70 mt-0.5">{{ $alert['message'] }}</p>
                    </div>
                </div>
                @else
                <div class="flex items-start gap-3 p-3 rounded-lg bg-yellow-500/10 border border-yellow-500/20">
                    <svg class="size-4 text-yellow-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-yellow-300">{{ $alert['title'] }}</p>
                        <p class="text-xs text-yellow-400/70 mt-0.5">{{ $alert['message'] }}</p>
                    </div>
                </div>
                @endif
                @empty
                <div class="flex items-start gap-3 p-3 rounded-lg bg-zinc-800 border border-zinc-700">
                    <svg class="size-4 text-green-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-zinc-300">Operação normal</p>
                        <p class="text-xs text-zinc-500 mt-0.5">Nenhum alerta ativo no momento.</p>
                    </div>
                </div>
                @endforelse
            </div>
        </div>

    </div>
</div>
