<?php

namespace App\Livewire\Concerns;

use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * Period filter of a report screen: a preset (today, last 7 days, last 30
 * days) or a custom range of up to MAX_DAYS that the server validates. The
 * applied range is locked, so the browser can only pick a preset or send a
 * custom range. The component checks its roles in authorizeReport() (Livewire
 * actions do not go through the route middleware again) and clears its
 * computed report in periodChanged().
 */
trait FiltersReportPeriod
{
    #[Locked]
    public string $period = '7d';

    #[Locked]
    public string $rangeFrom = '';

    #[Locked]
    public string $rangeTo = '';

    /** Custom range inputs (Y-m-d). */
    public string $from = '';

    public string $to = '';

    abstract protected function authorizeReport(): void;

    abstract protected function periodChanged(): void;

    /** Longest custom range, in days. */
    protected function maxReportDays(): int
    {
        return 366;
    }

    #[Computed]
    public function presets(): array
    {
        return [
            'today' => 'Hoje',
            '7d'    => 'Últimos 7 dias',
            '30d'   => 'Últimos 30 dias',
        ];
    }

    public function setPeriod(string $period): void
    {
        $this->authorizeReport();
        $this->resetErrorBag();
        $this->applyPreset(array_key_exists($period, $this->presets) ? $period : '7d');
    }

    public function applyCustom(): void
    {
        $this->authorizeReport();

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

        if ($from->diffInDays($to) + 1 > $this->maxReportDays()) {
            $this->addError('to', 'Escolha um período de até '.$this->maxReportDays().' dias.');

            return;
        }

        $this->period    = 'custom';
        $this->rangeFrom = $from->toDateString();
        $this->rangeTo   = $to->toDateString();
        $this->periodChanged();
    }

    protected function applyPreset(string $period): void
    {
        $today = CarbonImmutable::today();

        [$from, $to] = match ($period) {
            'today' => [$today, $today],
            '30d'   => [$today->subDays(29), $today],
            default => [$today->subDays(6), $today],
        };

        $this->period    = $period;
        $this->rangeFrom = $from->toDateString();
        $this->rangeTo   = $to->toDateString();
        $this->from      = $this->rangeFrom;
        $this->to        = $this->rangeTo;
        $this->periodChanged();
    }

    protected function rangeFromDate(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $this->rangeFrom)->startOfDay();
    }

    protected function rangeToDate(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $this->rangeTo)->startOfDay();
    }
}
