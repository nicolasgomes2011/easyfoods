{{--
    One delivery on the driver panel: number, customer, total, optional items
    summary and the address. Actions go in the default slot, a status tag in `badge`.
    `contact` adds "Ligar para o cliente" / "Abrir no mapa" (the driver's own deliveries).
--}}
@props(['order', 'accent' => 'border-zinc-800', 'items' => false, 'contact' => false])

<div {{ $attributes->merge(['class' => "bg-zinc-900 border {$accent} rounded-xl p-4"]) }}>
    <div class="flex items-start justify-between gap-3 mb-3">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-xs font-mono text-zinc-500">#{{ $order->number }}</span>
                {{ $badge ?? '' }}
            </div>
            <p class="text-sm font-semibold text-white">{{ $order->customer_name }}</p>
            @if($order->customer_phone)
            <p class="text-xs text-zinc-400 mt-0.5">{{ $order->customer_phone }}</p>
            @endif
        </div>
        <p class="text-sm font-bold text-zinc-200 tabular-nums shrink-0">
            R$ {{ number_format($order->total, 2, ',', '.') }}
        </p>
    </div>

    @if($items)
    {{-- Items summary --}}
    <div class="bg-zinc-800 rounded-lg px-3 py-2 mb-3">
        @foreach($order->items->take(3) as $item)
        <p class="text-xs text-zinc-300">{{ $item->quantity }}× {{ $item->product_name }}</p>
        @endforeach
        @if($order->items->count() > 3)
        <p class="text-xs text-zinc-500 mt-0.5">+{{ $order->items->count() - 3 }} itens</p>
        @endif
    </div>
    @endif

    @if($order->delivery_address_street)
    <div class="bg-zinc-800/60 rounded-lg px-3 py-2 mb-3 text-xs text-zinc-300">
        <p>{{ $order->delivery_address_street }}, {{ $order->delivery_address_number }}
        @if($order->delivery_address_complement) – {{ $order->delivery_address_complement }}@endif</p>
        <p class="text-zinc-500 mt-0.5">{{ $order->delivery_address_neighborhood }} · {{ $order->delivery_address_city }}/{{ $order->delivery_address_state }}</p>
    </div>
    @endif

    @if($contact && ($order->customerPhoneUri() || $order->deliveryMapUrl()))
    {{-- One tap to call the customer or open the address in the maps app --}}
    <div class="flex gap-2 mb-3">
        @if($order->customerPhoneUri())
        <a href="{{ $order->customerPhoneUri() }}"
           class="flex-1 inline-flex items-center justify-center gap-1.5 py-2 text-xs font-semibold text-zinc-200 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 rounded-lg transition">
            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
            </svg>
            Ligar para o cliente
        </a>
        @endif
        @if($order->deliveryMapUrl())
        <a href="{{ $order->deliveryMapUrl() }}" target="_blank" rel="noopener noreferrer"
           class="flex-1 inline-flex items-center justify-center gap-1.5 py-2 text-xs font-semibold text-zinc-200 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 rounded-lg transition">
            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Abrir no mapa
        </a>
        @endif
    </div>
    @endif

    {{ $slot }}
</div>
