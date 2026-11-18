<?php
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Actions\Reports\BuildSalesReport;
use App\Enums\UserRole;
use App\Livewire\Concerns\FiltersReportPeriod;
use App\Support\CurrentRestaurant;
use App\Support\SalesReportCsv;

/*
 * Sales report (Relatórios › Vendas por período): revenue, number of orders
 * and average ticket in a period, by day, week or month, compared with the
 * period of the same length right before it (BuildSalesReport). Canceled and
 * draft orders are left out. Admin and manager only, checked again on every
 * action. "Exportar CSV" downloads the same lines as the table.
 */
new #[Layout('components.layouts.app')] class extends Component {
    use FiltersReportPeriod;

    /** day | week | month (BuildSalesReport::GROUPS). */
    public string $group = 'day';

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
        unset($this->report, $this->change);
    }

    #[Computed]
    public function groups(): array
    {
        return ['day' => 'Por dia', 'week' => 'Por semana', 'month' => 'Por mês'];
    }

    public function setGroup(string $group): void
    {
        $this->authorizeReport();
        $this->group = in_array($group, BuildSalesReport::GROUPS, true) ? $group : 'day';
        $this->periodChanged();
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

        return app(BuildSalesReport::class)->execute(
            $restaurantId,
            $this->rangeFromDate(),
            $this->rangeToDate(),
            in_array($this->group, BuildSalesReport::GROUPS, true) ? $this->group : 'day',
        );
    }

    /** Revenue against the previous period: "+R$ 110,00 (+275%)"; null when both are zero. */
    #[Computed]
    public function change(): ?array
    {
        $report = $this->report;

        if ($report === null || ($report['revenue'] == 0.0 && $report['previous_revenue'] == 0.0)) {
            return null;
        }

        $difference = round($report['revenue'] - $report['previous_revenue'], 2);
        $percent    = $report['previous_revenue'] > 0 ? (int) round($difference / $report['previous_revenue'] * 100) : null;
        $money      = ($difference < 0 ? '-' : '+').'R$ '.number_format(abs($difference), 2, ',', '.');

        return [
            'difference' => $difference,
            'text'       => ($difference == 0.0 ? 'R$ 0,00' : $money)
                .($percent !== null ? ' ('.($percent > 0 ? '+' : '').$percent.'%)' : ''),
        ];
    }

    public function export()
    {
        $this->authorizeReport();

        $report = $this->report;

        if ($report === null) {
            return null;
        }

        $csv = SalesReportCsv::make($report);

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, SalesReportCsv::filename($report), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}; ?>

@php
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $report = $this->report;
@endphp

<div class="max-w-5xl">
    <div class="flex items-start justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Vendas por período</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Faturamento, pedidos e ticket médio, sem pedidos cancelados.</p>
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
                <label for="sales-report-from" class="block text-xs font-medium text-zinc-400 mb-1">De</label>
                <input id="sales-report-from" type="date" wire:model="from"
                       class="bg-zinc-800 border border-zinc-700 text-white text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-500" />
            </div>
            <div>
                <label for="sales-report-to" class="block text-xs font-medium text-zinc-400 mb-1">Até</label>
                <input id="sales-report-to" type="date" wire:model="to"
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
    {{-- Summary: this period against the one before --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3">
            <p class="text-xs text-zinc-400">Faturamento de {{ $report['from']->format('d/m') }} a {{ $report['to']->format('d/m/Y') }}</p>
            <p class="text-2xl font-bold text-white tabular-nums mt-1"><span data-sales-revenue>{{ $money($report['revenue']) }}</span></p>
            <p class="text-xs text-zinc-500 mt-1">
                Período anterior ({{ $report['previous_from']->format('d/m') }} a {{ $report['previous_to']->format('d/m') }}):
                <span data-sales-previous-revenue>{{ $money($report['previous_revenue']) }}</span>
            </p>
            @if($this->change)
            <p data-sales-change class="text-xs font-medium tabular-nums mt-1 {{ $this->change['difference'] > 0 ? 'text-green-400' : ($this->change['difference'] < 0 ? 'text-red-400' : 'text-zinc-400') }}">
                {{ $this->change['text'] }}
            </p>
            @endif
        </div>
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3">
            <p class="text-xs text-zinc-400">Pedidos</p>
            <p class="text-2xl font-bold text-white tabular-nums mt-1"><span data-sales-orders>{{ $report['orders'] }}</span></p>
            <p class="text-xs text-zinc-500 mt-1">Período anterior: <span data-sales-previous-orders>{{ $report['previous_orders'] }}</span></p>
        </div>
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3">
            <p class="text-xs text-zinc-400">Ticket médio</p>
            <p class="text-2xl font-bold text-white tabular-nums mt-1"><span data-sales-ticket>{{ $money($report['average_ticket']) }}</span></p>
            <p class="text-xs text-zinc-500 mt-1">Período anterior: <span data-sales-previous-ticket>{{ $money($report['previous_average_ticket']) }}</span></p>
        </div>
    </div>

    <section class="bg-zinc-900 border border-zinc-800 rounded-xl">
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b border-zinc-800">
            <div class="flex flex-wrap gap-2" role="group" aria-label="Agrupar por">
                @foreach($this->groups as $value => $label)
                <button type="button" wire:click="setGroup('{{ $value }}')" data-sales-group="{{ $value }}"
                        aria-pressed="{{ $group === $value ? 'true' : 'false' }}"
                        class="min-h-10 rounded-xl border px-3 text-sm font-medium transition {{ $group === $value ? 'border-orange-500 bg-orange-500/15 text-white' : 'border-zinc-700 bg-zinc-800 text-zinc-300 hover:border-zinc-500' }}">
                    {{ $label }}
                </button>
                @endforeach
            </div>
            <button type="button" wire:click="export"
                    class="min-h-10 rounded-xl border border-zinc-700 bg-zinc-800 px-4 text-sm font-medium text-zinc-200 hover:border-zinc-500">
                Exportar CSV
            </button>
        </div>

        @if($report['orders'] === 0)
        <p class="px-4 py-8 text-center text-sm text-zinc-500">Nenhum pedido neste período.</p>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-zinc-400">
                        <th class="px-4 py-2 font-medium">Período</th>
                        <th class="px-4 py-2 font-medium text-right">Pedidos</th>
                        <th class="px-4 py-2 font-medium text-right">Faturamento</th>
                        <th class="px-4 py-2 font-medium text-right">Ticket médio</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @foreach($report['rows'] as $row)
                    <tr data-sales-row="{{ $row['start'] }}" wire:key="sales-row-{{ $group }}-{{ $row['start'] }}" class="{{ $row['orders'] === 0 ? 'text-zinc-500' : 'text-zinc-200' }}">
                        <td class="px-4 py-2 whitespace-nowrap">{{ $row['label'] }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $row['orders'] }}</td>
                        <td class="px-4 py-2 text-right tabular-nums whitespace-nowrap">{{ $money($row['revenue']) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums whitespace-nowrap">{{ $money($row['average_ticket']) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-zinc-700 font-semibold text-white">
                        <td class="px-4 py-2">Total</td>
                        <td class="px-4 py-2 text-right tabular-nums">{{ $report['orders'] }}</td>
                        <td class="px-4 py-2 text-right tabular-nums whitespace-nowrap">{{ $money($report['revenue']) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums whitespace-nowrap">{{ $money($report['average_ticket']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
    @endif
</div>
