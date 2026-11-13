{{--
    One line of the waste screen list. A correction shows the difference it records
    and the entry it corrects; an original entry shows its quantity after corrections
    and "Corrigir" (wired to the waste/index component: startCorrecting). Expects
    `adjustments_sum_quantity` loaded (withSum). Costs only with `canSeeCosts`.
--}}
@props(['entry', 'canSeeCosts' => false])

@if($entry->isAdjustment())
<div {{ $attributes->merge(['class' => 'px-4 py-3 flex items-start gap-3']) }} data-waste-adjustment="{{ $entry->id }}">
    <span class="text-xs text-zinc-500 tabular-nums w-10 shrink-0 pt-0.5">{{ $entry->created_at->format('H:i') }}</span>
    <div class="min-w-0 flex-1">
        <p class="text-sm text-zinc-200">
            <span class="inline-flex items-center rounded-full px-2 py-0.5 mr-1 text-[10px] font-semibold uppercase tracking-wide bg-amber-500/15 text-amber-300">Correção</span>
            {{ $entry->ingredient_name }} · {{ $entry->formattedQuantity() }}
        </p>
        <p class="text-xs text-zinc-500 mt-0.5">
            {{ $entry->note }}
            @if($entry->adjusts)
            · lançamento de {{ $entry->adjusts->created_at->format($entry->adjusts->created_at->isToday() ? 'H:i' : 'd/m H:i') }}
            @endif
            · {{ $entry->recordedBy?->name ?? '—' }}
        </p>
    </div>
    @if($canSeeCosts)
    <span class="text-sm text-zinc-300 tabular-nums whitespace-nowrap">{{ \App\Models\WasteEntry::formatMoney($entry->totalCost()) }}</span>
    @endif
</div>
@else
@php $effective = $entry->effectiveQuantity(); @endphp
<div {{ $attributes->merge(['class' => 'px-4 py-3 flex items-start gap-3']) }} data-waste-entry="{{ $entry->id }}">
    <span class="text-xs text-zinc-500 tabular-nums w-10 shrink-0 pt-0.5">{{ $entry->created_at->format('H:i') }}</span>
    <div class="min-w-0 flex-1">
        <p class="text-sm font-medium text-white">{{ $entry->ingredient_name }} · {{ $entry->formattedQuantity() }}</p>
        <p class="text-xs text-zinc-400 mt-0.5">{{ $entry->reason->label() }} · {{ $entry->recordedBy?->name ?? '—' }}</p>
        @if(filled($entry->note))
        <p class="text-xs text-zinc-500 mt-0.5">{{ $entry->note }}</p>
        @endif
        @if($effective !== round((float) $entry->quantity, 3))
        <p class="text-xs text-amber-300 mt-0.5">Corrigido: agora {{ $entry->formattedQuantity($effective) }}</p>
        @endif
    </div>
    @if($canSeeCosts)
    <span class="text-sm text-zinc-300 tabular-nums whitespace-nowrap">{{ \App\Models\WasteEntry::formatMoney($entry->totalCost()) }}</span>
    @endif
    <button wire:click="startCorrecting({{ $entry->id }})"
            class="min-h-9 text-xs text-zinc-300 hover:text-white bg-zinc-800 hover:bg-zinc-700 rounded-lg px-3 py-1.5 transition">
        Corrigir
    </button>
</div>
@endif
