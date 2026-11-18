{{--
    One order on the kitchen queue (kitchen/index), sized for a tablet read at 1–2 m:
    order type / table and notes stand out, action buttons are at least 48px tall
    (wired to the kitchen component: startPreparing, toggleItemPrepared, markReady).
    In preparation, each item has a checklist box; "Marcar pronto" is enabled only
    when every item is ticked. Expects `items.addons` and the removed `itemChanges` loaded.
--}}
@props(['order'])

@php
    $inPrep  = $order->status === \App\Enums\OrderStatus::InPreparation;
    $minutes = (int) $order->created_at->diffInMinutes(now());
    $urgent  = $inPrep && $minutes >= 20;
    $canAct  = auth()->user()?->can('transitionInKitchen', $order) ?? false;

    // Kitchen checklist (in preparation only)
    $itemCount     = $order->items->count();
    $preparedCount = $order->items->filter(fn ($item) => $item->isPrepared())->count();
    $allPrepared   = $preparedCount === $itemCount;
    $showChecklist = $inPrep && $canAct;
@endphp

<div {{ $attributes }} data-kitchen-order="{{ $order->id }}" @if(! $inPrep) data-new-order="{{ $order->id }}" @endif
     class="bg-zinc-900 border {{ $inPrep || $urgent ? 'border-orange-500/50' : 'border-yellow-400/50 ring-1 ring-yellow-400/30' }} rounded-xl overflow-hidden">
    <div class="flex items-center justify-between gap-2 px-4 py-3 border-b {{ $inPrep || $urgent ? 'border-orange-500/30 bg-orange-500/5' : 'border-zinc-800' }}">
        <div class="flex flex-wrap items-center gap-2 min-w-0">
            <span class="text-lg font-bold font-mono {{ $inPrep || $urgent ? 'text-orange-400' : 'text-white' }}">
                #{{ $order->number }}
            </span>
            {{-- Where the order goes: the first thing the cook needs --}}
            <span class="text-base font-semibold text-white bg-zinc-800 border border-zinc-700 rounded-lg px-2 py-0.5">
                <x-orders.delivery-type :order="$order" />
            </span>
            @if($inPrep)
            <span class="text-xs font-semibold text-orange-400 bg-orange-400/10 border border-orange-400/20 rounded-full px-2 py-0.5">
                Em preparo
            </span>
            @else
            <span class="text-xs font-semibold text-yellow-400 bg-yellow-400/10 border border-yellow-400/20 rounded-full px-2 py-0.5">
                Aguardando
            </span>
            @endif
        </div>
        <div class="flex items-center gap-1.5 text-sm shrink-0 {{ $urgent ? 'text-red-400' : 'text-zinc-400' }}">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ $minutes }} min
        </div>
    </div>
    @if($inPrep && $order->preparation_started_at)
    {{-- Prep timer: counts from the start of preparation, red past the estimate --}}
    @php
        $elapsed = max(0, (int) $order->preparation_started_at->diffInSeconds(now()));
        $late = $order->estimated_ready_at && now()->gte($order->estimated_ready_at);
    @endphp
    <div class="px-4 pt-3 flex items-center justify-between text-sm"
         x-data="{ start: {{ $order->preparation_started_at->getTimestampMs() }}, due: {{ $order->estimated_ready_at?->getTimestampMs() ?? 'null' }}, now: Date.now() }"
         x-init="setInterval(() => now = Date.now(), 1000)">
        <span data-prep-timer class="font-mono font-semibold tabular-nums {{ $late ? 'text-red-400' : 'text-zinc-200' }}"
              :class="{ 'text-red-400': due && now >= due, 'text-zinc-200': ! (due && now >= due) }"
              x-text="(s => String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0'))(Math.max(0, Math.floor((now - start) / 1000)))">{{ sprintf('%02d:%02d', intdiv($elapsed, 60), $elapsed % 60) }}</span>
        @if($order->estimated_ready_at)
        <span class="text-zinc-500">Previsto {{ $order->estimated_ready_at->format('H:i') }}</span>
        @endif
    </div>
    @endif
    @if($showChecklist && $itemCount > 0)
    <p data-kitchen-progress="{{ $order->id }}" class="px-4 pt-3 text-sm font-medium {{ $allPrepared ? 'text-green-400' : 'text-zinc-400' }}">
        {{ $preparedCount }} de {{ $itemCount }} itens
    </p>
    @endif
    <div class="px-4 py-3 space-y-2">
        @foreach($order->items as $item)
        @if($item->wasAddedLater())
        {{-- Added by the front of house after the order was placed --}}
        <div data-added-item="{{ $item->id }}" class="flex items-center gap-2 rounded-md bg-yellow-400/10 border border-yellow-400/30 px-2 py-1 -mx-2">
            <x-kitchen.item-check :item="$item" :checklist="$showChecklist" dot-class="bg-yellow-400 animate-pulse" />
            <span class="text-base font-medium {{ $showChecklist && $item->isPrepared() ? 'text-yellow-200/60 line-through' : 'text-yellow-200' }}">{{ $item->displayName() }} ×{{ $item->quantity }}</span>
            <span class="ml-auto text-xs font-semibold text-yellow-400 uppercase tracking-wide">Novo · {{ $item->added_at->format('H:i') }}</span>
        </div>
        @else
        <div class="flex items-center gap-2">
            <x-kitchen.item-check :item="$item" :checklist="$showChecklist" />
            <span class="text-base {{ $showChecklist && $item->isPrepared() ? 'text-zinc-500 line-through' : 'text-zinc-200' }}">{{ $item->displayName() }} ×{{ $item->quantity }}</span>
        </div>
        @endif
        {{-- What to prepare: addons (frozen snapshot) and the item note --}}
        @if($item->addons->isNotEmpty() || filled($item->notes))
        <div data-item-details="{{ $item->id }}" class="pl-4 space-y-1">
            @foreach($item->addons as $addon)
            <p class="text-sm text-zinc-400">+ {{ filled($addon->addon_group_name) ? $addon->addon_group_name.': ' : '' }}{{ $addon->addon_option_name }}</p>
            @endforeach
            @if(filled($item->notes))
            <p class="text-sm font-semibold text-orange-200 bg-orange-500/10 border border-orange-500/30 rounded-md px-2 py-1">Obs: {{ $item->notes }}</p>
            @endif
        </div>
        @endif
        @endforeach
        @foreach($order->removalsSeenByKitchen() as $removal)
        {{-- Removed by the front of house after the kitchen got the order: stop preparing it --}}
        <div data-removed-item="{{ $removal->id }}" class="rounded-md bg-red-500/10 border border-red-500/30 px-2 py-1 -mx-2">
            <div class="flex items-center gap-2">
                <span class="size-2 rounded-full bg-red-400 shrink-0"></span>
                <span class="text-base font-medium text-red-300 line-through">{{ $removal->displayName() }} ×{{ $removal->quantity }}</span>
                <span class="ml-auto text-xs font-semibold text-red-400 uppercase tracking-wide">Removido · {{ $removal->changed_at->format('H:i') }}</span>
            </div>
            @if(filled($removal->reason))
            <p class="pl-4 text-sm text-red-300/80">Motivo: {{ $removal->reason }}</p>
            @endif
        </div>
        @endforeach
    </div>
    <div class="px-4 py-3 border-t border-zinc-800 flex items-center justify-between gap-3">
        <span class="text-xs text-zinc-500 tabular-nums">
            R$ {{ number_format($order->total, 2, ',', '.') }}
        </span>
        @if($canAct)
            @if($order->status === \App\Enums\OrderStatus::Confirmed)
            <button
                data-kitchen-action="start"
                wire:click="startPreparing({{ $order->id }})"
                wire:loading.attr="disabled"
                wire:target="startPreparing({{ $order->id }})"
                class="inline-flex min-h-12 items-center justify-center gap-1.5 text-base font-semibold text-orange-300 bg-orange-400/10 hover:bg-orange-400/20 border border-orange-400/30 rounded-xl px-5 transition disabled:opacity-50">
                Iniciar preparo
            </button>
            @elseif($order->status === \App\Enums\OrderStatus::InPreparation && $allPrepared)
            <button
                data-kitchen-action="ready"
                wire:click="markReady({{ $order->id }})"
                wire:loading.attr="disabled"
                wire:target="markReady({{ $order->id }})"
                class="inline-flex min-h-12 items-center justify-center gap-1.5 text-base font-semibold text-green-300 bg-green-400/10 hover:bg-green-400/20 border border-green-400/30 rounded-xl px-5 transition disabled:opacity-50">
                Marcar pronto
            </button>
            @elseif($order->status === \App\Enums\OrderStatus::InPreparation)
            {{-- Items still to tick: the server refuses markReady too --}}
            <button type="button" disabled
                data-kitchen-action="ready"
                data-kitchen-ready-blocked="{{ $order->id }}"
                title="Marque todos os itens para liberar"
                class="inline-flex min-h-12 items-center justify-center gap-1.5 text-base font-semibold text-zinc-500 bg-zinc-800 border border-zinc-700 rounded-xl px-5 cursor-not-allowed">
                Marcar pronto
            </button>
            @endif
        @endif
    </div>
</div>
