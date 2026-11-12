{{--
    One delivery on the driver panel: number, customer, total, optional items
    summary and the address. Actions go in the default slot, a status tag in `badge`.
--}}
@props(['order', 'accent' => 'border-zinc-800', 'items' => false])

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

    {{ $slot }}
</div>
