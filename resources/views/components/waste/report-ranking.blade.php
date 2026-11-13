{{--
    One ranking of the waste report (reports/waste): label, optional quantity,
    money lost and a bar with the share of the period total. Rows come from
    App\Actions\Waste\BuildWasteReport, already sorted by cost.
--}}
@props(['title', 'key', 'rows' => [], 'withQuantity' => false])

<section {{ $attributes->merge(['class' => 'bg-zinc-900 border border-zinc-800 rounded-xl p-4']) }} data-waste-ranking="{{ $key }}">
    <h2 class="text-sm font-semibold text-white mb-3">{{ $title }}</h2>

    <ol class="space-y-3">
        @foreach($rows as $row)
        <li>
            <div class="flex items-baseline justify-between gap-3 text-sm">
                <span class="text-zinc-200 min-w-0 truncate">
                    {{ $row['label'] }}
                    @if($withQuantity)
                    <span class="text-zinc-500 tabular-nums">· {{ \App\Models\WasteEntry::formatQuantity($row['quantity']) }} {{ $row['unit'] }}</span>
                    @endif
                </span>
                <span class="text-zinc-200 tabular-nums whitespace-nowrap">
                    {{ \App\Models\WasteEntry::formatMoney($row['cost']) }}
                    <span class="text-xs text-zinc-500">{{ number_format($row['share'], 0, ',', '.') }}%</span>
                </span>
            </div>
            <div class="mt-1 h-1.5 rounded-full bg-zinc-800 overflow-hidden" aria-hidden="true">
                <div class="h-full rounded-full bg-orange-500/70" style="width: {{ max(0, min(100, $row['share'])) }}%"></div>
            </div>
        </li>
        @endforeach
    </ol>
</section>
