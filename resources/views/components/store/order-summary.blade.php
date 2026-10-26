@props(['cart', 'subtotal', 'deliveryFee', 'total'])

<div class="bg-white border border-zinc-200 rounded-2xl p-5 sticky top-24">
    <h2 class="text-sm font-semibold text-zinc-700 mb-4">Resumo do pedido</h2>

    <div class="space-y-2 mb-4">
        @if($cart)
        @foreach($cart->items as $item)
        <div class="flex justify-between text-sm text-zinc-600">
            <span class="flex-1 truncate">
                {{ $item->quantity }}× {{ $item->product?->name }}{{ $item->variant ? ' ('.$item->variant->name.')' : '' }}
                @if($item->addons->isNotEmpty())
                <span class="block text-xs text-zinc-400">{{ $item->addons->map(fn ($a) => $a->option?->name)->filter()->join(', ') }}</span>
                @endif
            </span>
            <span class="ml-2 tabular-nums">R$ {{ number_format($item->lineTotal(), 2, ',', '.') }}</span>
        </div>
        @endforeach
        @endif
    </div>

    <div class="border-t border-zinc-100 pt-3 space-y-1.5 text-sm">
        <div class="flex justify-between text-zinc-500">
            <span>Subtotal</span>
            <span class="tabular-nums">R$ {{ number_format($subtotal, 2, ',', '.') }}</span>
        </div>
        <div class="flex justify-between text-zinc-500">
            <span>Taxa de entrega</span>
            <span class="tabular-nums">{{ $deliveryFee > 0 ? 'R$ ' . number_format($deliveryFee, 2, ',', '.') : 'Grátis' }}</span>
        </div>
        <div class="flex justify-between font-bold text-zinc-800 pt-2 border-t border-zinc-100 text-base">
            <span>Total</span>
            <span class="tabular-nums">R$ {{ number_format($total, 2, ',', '.') }}</span>
        </div>
    </div>

    <button type="submit" wire:loading.attr="disabled"
            class="w-full mt-5 py-3.5 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-2xl transition text-sm">
        <span wire:loading.remove wire:target="placeOrder">Confirmar pedido</span>
        <span wire:loading wire:target="placeOrder">Processando…</span>
    </button>
</div>
