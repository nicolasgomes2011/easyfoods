{{--
    Dashboard "Entregadores ativos": each active driver (available, on pause or
    busy) and the orders they hold right now. `drivers` comes from
    App\Livewire\Dashboard::activeDrivers().
--}}
@props(['drivers'])

@php
$states = [
    'en_route'  => ['label' => 'Em rota',    'badge' => 'bg-indigo-500/15 text-indigo-400', 'dot' => 'bg-indigo-400'],
    'pickup'    => ['label' => 'A retirar',  'badge' => 'bg-orange-500/15 text-orange-400', 'dot' => 'bg-orange-400'],
    'available' => ['label' => 'Disponível', 'badge' => 'bg-green-500/15 text-green-400',   'dot' => 'bg-green-400'],
    'paused'    => ['label' => 'Em pausa',   'badge' => 'bg-zinc-800 text-zinc-400',        'dot' => 'bg-zinc-500'],
];
$counts    = $drivers->countBy('state');
$busy      = ($counts['en_route'] ?? 0) + ($counts['pickup'] ?? 0);
$available = $counts['available'] ?? 0;
@endphp

<div {{ $attributes->merge(['class' => 'bg-zinc-900 border border-zinc-800 rounded-xl']) }}>
    <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-4 border-b border-zinc-800">
        <h2 class="text-sm font-semibold text-white">Entregadores ativos</h2>
        @if($drivers->isNotEmpty())
        <p class="text-xs text-zinc-500 tabular-nums">
            {{ $busy }} com pedido · {{ $available }} {{ $available === 1 ? 'disponível' : 'disponíveis' }} · {{ $counts['paused'] ?? 0 }} em pausa
        </p>
        @endif
    </div>

    @if($drivers->isEmpty())
    <div class="px-5 py-8 text-center">
        <p class="text-zinc-500 text-sm">Nenhum entregador cadastrado.</p>
    </div>
    @else
    <div class="divide-y divide-zinc-800/70">
        @foreach($drivers as $driver)
        @php $state = $states[$driver['state']]; @endphp
        <div data-driver="{{ $driver['id'] }}" data-driver-state="{{ $driver['state'] }}" class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="size-1.5 rounded-full shrink-0 {{ $state['dot'] }}"></span>
                <p class="text-sm font-medium text-white truncate">{{ $driver['name'] }}</p>
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium shrink-0 {{ $state['badge'] }}">{{ $state['label'] }}</span>
            </div>
            @if($driver['deliveries']->isNotEmpty())
            {{-- Who has each order: en route, or accepted and waiting at the counter --}}
            <div class="flex flex-wrap gap-1.5">
                @foreach($driver['deliveries'] as $delivery)
                <span data-driver-order="{{ $delivery->id }}" class="text-xs font-mono text-zinc-300 bg-zinc-800 rounded-md px-2 py-0.5">#{{ $delivery->number }} · {{ $delivery->status === \App\Enums\OrderStatus::OutForDelivery ? 'em rota' : 'a retirar' }}</span>
                @endforeach
            </div>
            @endif
        </div>
        @endforeach
    </div>
    @endif
</div>
