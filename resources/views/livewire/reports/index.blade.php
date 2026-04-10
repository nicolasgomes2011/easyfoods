<?php
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    // Fase futura: relatórios de vendas, itens mais pedidos, horários de pico
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Relatórios</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Indicadores de desempenho operacional e de vendas</p>
        </div>
        <span class="text-xs text-zinc-400 bg-zinc-800 border border-zinc-700 rounded-lg px-3 py-1.5">
            Fase futura
        </span>
    </div>

    {{-- Report cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
        @foreach([
            ['title' => 'Vendas por período',      'desc' => 'Faturamento diário, semanal e mensal com comparativo de períodos anteriores.',          'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
            ['title' => 'Itens mais pedidos',      'desc' => 'Ranking de produtos por volume de pedidos e receita gerada no período selecionado.',     'icon' => 'M4 6h16M4 10h16M4 14h16M4 18h16'],
            ['title' => 'Horários de pico',        'desc' => 'Distribuição de pedidos por hora do dia e dia da semana para planejamento de equipe.',  'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['title' => 'Tempo de preparo',        'desc' => 'Tempo médio da cozinha por produto, por categoria e por turno de trabalho.',             'icon' => 'M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z'],
            ['title' => 'Desempenho do salão',     'desc' => 'Taxa de ocupação das mesas, tempo médio de permanência e rotatividade por turno.',      'icon' => 'M3 10h18M3 14h18M10 3v18M14 3v18'],
            ['title' => 'Cancelamentos e perdas',  'desc' => 'Pedidos cancelados, motivos declarados e impacto financeiro por período.',               'icon' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ] as $report)
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-5 opacity-70 cursor-default">
            <div class="flex items-start gap-3 mb-3">
                <div class="size-8 rounded-lg bg-zinc-800 flex items-center justify-center shrink-0">
                    <svg class="size-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $report['icon'] }}"/>
                    </svg>
                </div>
                <h3 class="text-sm font-semibold text-white leading-tight pt-1">{{ $report['title'] }}</h3>
            </div>
            <p class="text-xs text-zinc-500 leading-relaxed">{{ $report['desc'] }}</p>
            <div class="mt-4">
                <span class="text-xs text-zinc-600 bg-zinc-800 rounded-full px-2.5 py-1">Em desenvolvimento</span>
            </div>
        </div>
        @endforeach
    </div>

    <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-5 text-center">
        <p class="text-sm text-zinc-400 mb-1">Módulo de relatórios planejado para Fase 2</p>
        <p class="text-xs text-zinc-600">
            Requer dados reais dos modelos <code class="text-zinc-500">Order</code>, <code class="text-zinc-500">OrderItem</code> e <code class="text-zinc-500">Payment</code>.
            Implementar com agregações por período e exportação CSV.
        </p>
    </div>
</div>
