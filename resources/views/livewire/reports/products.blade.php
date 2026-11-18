<?php
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Actions\Reports\BuildProductsReport;
use App\Enums\UserRole;
use App\Livewire\Concerns\FiltersReportPeriod;
use App\Support\CurrentRestaurant;
use App\Support\ProductsReportCsv;

/*
 * Products and peak hours report (Relatórios › Itens mais pedidos e horários
 * de pico): top products by quantity and by revenue, and orders by hour of the
 * day and by day of the week, in a period (BuildProductsReport). Canceled and
 * draft orders are left out. Admin and manager only, checked again on every
 * action. "Exportar CSV" downloads the same lines as the tables.
 */
new #[Layout('components.layouts.app')] class extends Component {
    use FiltersReportPeriod;

    public function mount(): void
    {
        $this->authorizeReport();
        $this->applyPreset('7d');
    }

    protected function authorizeReport(): void
    {
        abort_unless(
            auth()->user()?->hasAnyRole(UserRole::Admin, UserRole::Manager) ?? false,
            403
        );
    }

    protected function periodChanged(): void
    {
        unset($this->report);
    }

    #[Computed]
    public function report(): ?array
    {
        // Also on a plain property update (custom range inputs), which runs no action.
        $this->authorizeReport();

        $restaurantId = CurrentRestaurant::id();

        if ($restaurantId === null) {
            return null;
        }

        return app(BuildProductsReport::class)->execute($restaurantId, $this->rangeFromDate(), $this->rangeToDate());
    }

    public function export()
    {
        $this->authorizeReport();

        $report = $this->report;

        if ($report === null) {
            return null;
        }

        $csv = ProductsReportCsv::make($report);

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, ProductsReportCsv::filename($report), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}; ?>

@php
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $report = $this->report;
    $weekdays = \App\Actions\Reports\BuildProductsReport::WEEKDAYS;
@endphp

<div class="max-w-5xl">
    <div class="flex items-start justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Itens mais pedidos e horários de pico</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Produtos que mais vendem e quando os pedidos chegam, sem pedidos cancelados.</p>
        </div>
        <a href="{{ route('admin.reports.index') }}" wire:navigate class="text-sm text-zinc-400 hover:text-white whitespace-nowrap">← Relatórios</a>
    </div>

    {{-- Period: presets or a custom range --}}
    <section class="bg-zinc-900 border border-zinc-800 rounded-xl p-4 mb-6">
        <div class="flex flex-wrap gap-2 mb-4" role="group" aria-label="Período do relatório">
            @foreach($this->presets as $value => $label)
            <button type="button" wire:click="setPeriod('{{ $value }}')" data-report-period="{{ $value }}"
                    aria-pressed="{{ $period === $value ? 'true' : 'false' }}"
                    class="min-h-10 rounded-xl border px-4 text-sm font-medium transition {{ $period === $value ? 'border-orange-500 bg-orange-500/15 text-white' : 'border-zinc-700 bg-zinc-800 text-zinc-300 hover:border-zinc-500' }}">
                {{ $label }}
            </button>
            @endforeach
        </div>

        <form wire:submit="applyCustom" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="products-report-from" class="block text-xs font-medium text-zinc-400 mb-1">De</label>
                <input id="products-report-from" type="date" wire:model="from"
                       class="bg-zinc-800 border border-zinc-700 text-white text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-500" />
            </div>
            <div>
                <label for="products-report-to" class="block text-xs font-medium text-zinc-400 mb-1">Até</label>
                <input id="products-report-to" type="date" wire:model="to"
                       class="bg-zinc-800 border border-zinc-700 text-white text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-500" />
            </div>
            <button type="submit"
                    class="min-h-10 rounded-xl border px-4 text-sm font-medium transition {{ $period === 'custom' ? 'border-orange-500 bg-orange-500/15 text-white' : 'border-zinc-700 bg-zinc-800 text-zinc-300 hover:border-zinc-500' }}">
                Aplicar período
            </button>
        </form>
        @error('from') <p class="text-red-400 text-xs mt-2">{{ $message }}</p> @enderror
        @error('to') <p class="text-red-400 text-xs mt-2">{{ $message }}</p> @enderror
    </section>

    @if($report !== null)
    {{-- Summary --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 flex-1">
            <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3">
                <p class="text-xs text-zinc-400">Pedidos de {{ $report['from']->format('d/m') }} a {{ $report['to']->format('d/m/Y') }}</p>
                <p class="text-2xl font-bold text-white tabular-nums mt-1"><span data-products-orders>{{ $report['orders'] }}</span></p>
            </div>
            @if($report['orders'] > 0)
            <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3">
                <p class="text-xs text-zinc-400">Horário de pico</p>
                <p class="text-2xl font-bold text-white tabular-nums mt-1"><span data-peak-hour>{{ \App\Support\ProductsReportCsv::hour($report['peak_hour']) }}</span></p>
                <p class="text-xs text-zinc-500 mt-1">{{ $report['by_hour'][$report['peak_hour']] }} pedido(s) nessa hora</p>
            </div>
            <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3">
                <p class="text-xs text-zinc-400">Dia mais movimentado</p>
                <p class="text-2xl font-bold text-white mt-1"><span data-peak-weekday>{{ $weekdays[$report['peak_weekday']] }}</span></p>
                <p class="text-xs text-zinc-500 mt-1">{{ $report['by_weekday'][$report['peak_weekday']] }} pedido(s) no período</p>
            </div>
            @endif
        </div>
        <button type="button" wire:click="export"
                class="min-h-10 rounded-xl border border-zinc-700 bg-zinc-800 px-4 text-sm font-medium text-zinc-200 hover:border-zinc-500">
            Exportar CSV
        </button>
    </div>

    @if($report['orders'] === 0)
    <p class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-8 text-center text-sm text-zinc-500">Nenhum pedido neste período.</p>
    @else
    {{-- Top products: by quantity and by revenue --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
        @foreach(['by_quantity' => ['Mais vendidos (quantidade)', 'data-top-quantity'], 'by_revenue' => ['Maior receita', 'data-top-revenue']] as $key => [$title, $attribute])
        <section class="bg-zinc-900 border border-zinc-800 rounded-xl">
            <h2 class="px-4 py-3 border-b border-zinc-800 text-sm font-semibold text-white">{{ $title }}</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-zinc-400">
                            <th class="px-4 py-2 font-medium w-8">#</th>
                            <th class="px-4 py-2 font-medium">Produto</th>
                            <th class="px-4 py-2 font-medium text-right">Qtd</th>
                            <th class="px-4 py-2 font-medium text-right">Receita</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800 text-zinc-200">
                        @foreach($report[$key] as $product)
                        <tr wire:key="{{ $key }}-{{ $loop->iteration }}">
                            <td class="px-4 py-2 text-zinc-500 tabular-nums">{{ $loop->iteration }}</td>
                            <td class="px-4 py-2" {{ $attribute }}="{{ $loop->iteration }}">{{ $product['name'] }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $product['quantity'] }}</td>
                            <td class="px-4 py-2 text-right tabular-nums whitespace-nowrap">{{ $money($product['revenue']) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @endforeach
    </div>

    {{-- Orders by hour of the day --}}
    @php $maxHour = max($report['by_hour']); @endphp
    <section class="bg-zinc-900 border border-zinc-800 rounded-xl p-4 mb-6">
        <h2 class="text-sm font-semibold text-white mb-4">Pedidos por hora do dia</h2>
        <div class="flex items-end gap-1 h-32" role="list" aria-label="Pedidos por hora do dia">
            @foreach($report['by_hour'] as $hour => $orders)
            <div role="listitem" class="flex-1 h-full flex flex-col justify-end" title="{{ \App\Support\ProductsReportCsv::hour($hour) }}: {{ $orders }} pedido(s)">
                <span class="sr-only">{{ \App\Support\ProductsReportCsv::hour($hour) }}: {{ $orders }} pedido(s)</span>
                <div data-hour="{{ $hour }}" data-orders="{{ $orders }}"
                     class="w-full rounded-t {{ $hour === $report['peak_hour'] ? 'bg-orange-500' : 'bg-zinc-600' }}"
                     style="height: {{ $orders > 0 ? max(4, (int) round($orders / $maxHour * 100)) : 0 }}%"></div>
            </div>
            @endforeach
        </div>
        <div class="flex gap-1 mt-1 text-[10px] text-zinc-500 tabular-nums" aria-hidden="true">
            @foreach($report['by_hour'] as $hour => $orders)
            <span class="flex-1 text-center">{{ $hour % 3 === 0 ? $hour : '' }}</span>
            @endforeach
        </div>
    </section>

    {{-- Orders by day of the week --}}
    @php $maxDay = max($report['by_weekday']); @endphp
    <section class="bg-zinc-900 border border-zinc-800 rounded-xl p-4">
        <h2 class="text-sm font-semibold text-white mb-4">Pedidos por dia da semana</h2>
        <ul class="space-y-2">
            @foreach($report['by_weekday'] as $day => $orders)
            <li class="flex items-center gap-3 text-sm">
                <span class="w-20 text-zinc-300">{{ $weekdays[$day] }}</span>
                <div class="flex-1 h-4 bg-zinc-800 rounded">
                    <div data-weekday="{{ $day }}" data-orders="{{ $orders }}"
                         class="h-4 rounded {{ $day === $report['peak_weekday'] ? 'bg-orange-500' : 'bg-zinc-600' }}"
                         style="width: {{ $maxDay > 0 ? (int) round($orders / $maxDay * 100) : 0 }}%"></div>
                </div>
                <span class="w-10 text-right text-zinc-200 tabular-nums">{{ $orders }}</span>
            </li>
            @endforeach
        </ul>
    </section>
    @endif
    @endif
</div>
