@props(['restaurant'])

{{-- Order type picker, only the types the store takes; wire:model targets the parent store.checkout component. --}}
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
