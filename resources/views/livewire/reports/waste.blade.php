<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;
use App\Actions\Waste\BuildWasteReport;
use App\Enums\UserRole;
use App\Models\WasteEntry;
use App\Support\CurrentRestaurant;
use Carbon\CarbonImmutable;

/*
 * Waste report (relatório de perdas): money lost in a period, ranked by
 * ingredient, category and reason, compared with the period of the same length
 * right before it. Admin and manager only; the kitchen records, it does not see
 * costs. The applied range is locked: the browser only picks a preset or sends
 * a custom range that the server validates.
 */
new #[Layout('components.layouts.app')] class extends Component {
    /** Longest custom range, in days. */
    private const MAX_DAYS = 366;

    #[Locked]
    public string $period = '7d';

    #[Locked]
    public string $rangeFrom = '';

    #[Locked]
    public string $rangeTo = '';

    /** Custom range inputs (Y-m-d). */
    public string $from = '';
    public string $to   = '';

    public function mount(): void
    {
        $this->authorizeRole();
        $this->applyPreset('7d');
    }

    /** Livewire actions do not go through the route middleware again: every action checks the role. */
    private function authorizeRole(): void
    {
        abort_unless(
            auth()->user()?->hasAnyRole(UserRole::Admin, UserRole::Manager) ?? false,
            403
        );
    }

    #[Computed]
    public function presets(): array
    {
        return [
            '7d'         => 'Últimos 7 dias',
            '30d'        => 'Últimos 30 dias',
            'month'      => 'Este mês',
            'last_month' => 'Mês passado',
        ];
    }

    public function setPeriod(string $period): void
    {
        $this->authorizeRole();
        $this->resetErrorBag();
        $this->applyPreset(array_key_exists($period, $this->presets) ? $period : '7d');
    }

    public function applyCustom(): void
    {
        $this->authorizeRole();

        $this->validate(
            [
                'from' => ['required', 'date_format:Y-m-d'],
                'to'   => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            ],
            ['to.after_or_equal' => 'A data final precisa ser igual ou depois da inicial.'],
            ['from' => 'data inicial', 'to' => 'data final'],
        );

        $from = CarbonImmutable::createFromFormat('Y-m-d', $this->from)->startOfDay();
        $to   = CarbonImmutable::createFromFormat('Y-m-d', $this->to)->startOfDay();

        if ($from->diffInDays($to) + 1 > self::MAX_DAYS) {
            $this->addError('to', 'Escolha um período de até '.self::MAX_DAYS.' dias.');

            return;
        }

        $this->period    = 'custom';
        $this->rangeFrom = $from->toDateString();
        $this->rangeTo   = $to->toDateString();
        unset($this->report);
    }

    private function applyPreset(string $period): void
    {
        $today = CarbonImmutable::today();

        [$from, $to] = match ($period) {
            '30d'        => [$today->subDays(29), $today],
            'month'      => [$today->startOfMonth(), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            default      => [$today->subDays(6), $today],
        };

        $this->period    = $period;
        $this->rangeFrom = $from->toDateString();
        $this->rangeTo   = $to->toDateString();
        $this->from      = $this->rangeFrom;
        $this->to        = $this->rangeTo;
        unset($this->report);
    }

    #[Computed]
    public function report(): ?array
    {
        $restaurantId = CurrentRestaurant::id();

        if ($restaurantId === null) {
            return null;
        }

        return app(BuildWasteReport::class)->execute(
            $restaurantId,
            CarbonImmutable::createFromFormat('Y-m-d', $this->rangeFrom),
            CarbonImmutable::createFromFormat('Y-m-d', $this->rangeTo),
        );
    }

    /** "+R$ 10,50 (+26%)" against the previous period; null when both are zero. */
    #[Computed]
    public function change(): ?array
    {
        $report = $this->report;

        if ($report === null || ($report['total'] == 0.0 && $report['previous_total'] == 0.0)) {
            return null;
        }

        $difference = round($report['total'] - $report['previous_total'], 2);
        $percent    = $report['previous_total'] > 0 ? (int) round($difference / $report['previous_total'] * 100) : null;

        return [
            'difference' => $difference,
            'text'       => ($difference > 0 ? '+' : '').WasteEntry::formatMoney($difference)
                .($percent !== null ? ' ('.($percent > 0 ? '+' : '').$percent.'%)' : ''),
        ];
    }
}; ?>

<div class="max-w-5xl">
    <div class="flex items-start justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Relatório de desperdício</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Quanto foi para o lixo, em quê e por quê.</p>
        </div>
        <a href="{{ route('admin.reports.index') }}" wire:navigate class="text-sm text-zinc-400 hover:text-white whitespace-nowrap">← Relatórios</a>
    </div>

    {{-- Period: presets or a custom range --}}
    <section class="bg-zinc-900 border border-zinc-800 rounded-xl p-4 mb-6">
        <div class="flex flex-wrap gap-2 mb-4" role="group" aria-label="Período do relatório">
            @foreach($this->presets as $value => $label)
            <button type="button" wire:click="setPeriod('{{ $value }}')" data-waste-period="{{ $value }}"
                    aria-pressed="{{ $period === $value ? 'true' : 'false' }}"
                    class="min-h-10 rounded-xl border px-4 text-sm font-medium transition {{ $period === $value ? 'border-orange-500 bg-orange-500/15 text-white' : 'border-zinc-700 bg-zinc-800 text-zinc-300 hover:border-zinc-500' }}">
                {{ $label }}
            </button>
            @endforeach
        </div>

        <form wire:submit="applyCustom" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="waste-report-from" class="block text-xs font-medium text-zinc-400 mb-1">De</label>
                <input id="waste-report-from" type="date" wire:model="from"
                       class="bg-zinc-800 border border-zinc-700 text-white text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-500" />
            </div>
            <div>
                <label for="waste-report-to" class="block text-xs font-medium text-zinc-400 mb-1">Até</label>
                <input id="waste-report-to" type="date" wire:model="to"
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

    @php $report = $this->report; @endphp

    @if($report === null || ($report['total'] == 0.0 && empty($report['by_ingredient'])))
    <div class="bg-zinc-900 border border-zinc-800 border-dashed rounded-xl px-5 py-10 text-center text-sm text-zinc-500 mb-6">
        Nenhum descarte registrado neste período.
    </div>
    @endif

    @if($report !== null)
    {{-- Summary: this period against the one before --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
        <div data-waste-total class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3">
            <p class="text-xs text-zinc-400">Perdido de {{ $report['from']->format('d/m') }} a {{ $report['to']->format('d/m/Y') }}</p>
            <p class="text-2xl font-bold text-white tabular-nums mt-1">{{ WasteEntry::formatMoney($report['total']) }}</p>
        </div>
        <div data-waste-previous class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3">
            <p class="text-xs text-zinc-400">Período anterior ({{ $report['previous_from']->format('d/m') }} a {{ $report['previous_to']->format('d/m') }})</p>
            <p class="text-2xl font-bold text-zinc-300 tabular-nums mt-1">{{ WasteEntry::formatMoney($report['previous_total']) }}</p>
        </div>
        <div data-waste-change class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3">
            <p class="text-xs text-zinc-400">Variação</p>
            @if($this->change)
            <p class="text-2xl font-bold tabular-nums mt-1 {{ $this->change['difference'] > 0 ? 'text-red-400' : ($this->change['difference'] < 0 ? 'text-green-400' : 'text-zinc-300') }}">
                {{ $this->change['text'] }}
            </p>
            @else
            <p class="text-2xl font-bold text-zinc-500 mt-1">—</p>
            @endif
        </div>
    </div>

    @if(! empty($report['by_ingredient']))
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <x-waste.report-ranking class="lg:col-span-2" title="Por insumo" key="ingredient" :rows="$report['by_ingredient']" with-quantity />
        <x-waste.report-ranking title="Por categoria" key="category" :rows="$report['by_category']" />
        <x-waste.report-ranking title="Por motivo" key="reason" :rows="$report['by_reason']" />
    </div>
    @endif
    @endif
</div>
