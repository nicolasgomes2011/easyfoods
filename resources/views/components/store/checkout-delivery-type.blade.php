@props(['restaurant', 'table' => null, 'kiosk' => false])

@if($table)
{{-- Modo mesa (QR Code): tipo e mesa fixos --}}
<div class="bg-white border border-zinc-200 rounded-2xl p-5">
    <h2 class="text-sm font-semibold text-zinc-700 mb-1">Pedido na mesa</h2>
    <p class="text-2xl font-bold text-orange-600">Mesa {{ $table->number }}</p>
    @unless($kiosk)
    <button type="button" wire:click="leaveTableMode"
            class="mt-2 text-xs text-zinc-400 hover:text-zinc-600 underline transition">
        Não está no restaurante? Pedir para entrega ou retirada
    </button>
    @endunless
</div>
@else
<div class="bg-white border border-zinc-200 rounded-2xl p-5">
    <h2 class="text-sm font-semibold text-zinc-700 mb-3">Como quer receber?</h2>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        @foreach([
            ['value' => 'delivery', 'label' => 'Delivery',     'show' => $restaurant?->accepts_delivery],
            ['value' => 'pickup',   'label' => 'Retirada',     'show' => $restaurant?->accepts_pickup],
            ['value' => 'dine_in',  'label' => 'Mesa (salão)', 'show' => $restaurant?->accepts_dine_in],
        ] as $type)
        @if($type['show'])
        <label class="relative cursor-pointer">
            <input type="radio" wire:model.live="deliveryType" value="{{ $type['value'] }}" class="sr-only peer" />
            <div class="border-2 rounded-xl px-3 py-2.5 text-sm text-center transition peer-checked:border-orange-500 peer-checked:text-orange-600 peer-checked:bg-orange-50 border-zinc-200 text-zinc-600 hover:border-zinc-300">
                {{ $type['label'] }}
            </div>
        </label>
        @endif
        @endforeach
    </div>
</div>
@endif
