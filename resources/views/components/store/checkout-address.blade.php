@props(['zones'])

{{-- Delivery address form; the wire: bindings target the parent store.checkout component. --}}
<div class="bg-white border border-zinc-200 rounded-2xl p-5 space-y-3">
    <h2 class="text-sm font-semibold text-zinc-700">Endereço de entrega</h2>

    @if($zones->isNotEmpty())
    <div>
        <select wire:model.live="deliveryZoneId"
                class="w-full bg-zinc-50 border border-zinc-200 text-sm rounded-xl px-3 py-2.5 text-zinc-800 focus:outline-none focus:ring-2 focus:ring-orange-400">
            <option value="">Selecione o bairro / zona *</option>
            @foreach($zones as $zone)
            <option value="{{ $zone->id }}">
                {{ $zone->name }}{{ $zone->neighborhood ? ' — '.$zone->neighborhood : '' }}
                ({{ (float) $zone->fee > 0 ? 'R$ '.number_format($zone->fee, 2, ',', '.') : 'Grátis' }})
            </option>
            @endforeach
        </select>
        @error('deliveryZoneId') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>
    @endif

    <div class="grid grid-cols-3 gap-3">
        <div class="col-span-2">
            <input wire:model="street" type="text" placeholder="Logradouro *"
                   class="w-full bg-zinc-50 border border-zinc-200 text-sm rounded-xl px-3 py-2.5 text-zinc-800 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-orange-400" />
            @error('street') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <input wire:model="addressNumber" type="text" placeholder="Número *"
                   class="w-full bg-zinc-50 border border-zinc-200 text-sm rounded-xl px-3 py-2.5 text-zinc-800 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-orange-400" />
            @error('addressNumber') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
    <div class="grid grid-cols-2 gap-3">
        <input wire:model="complement" type="text" placeholder="Complemento"
               class="w-full bg-zinc-50 border border-zinc-200 text-sm rounded-xl px-3 py-2.5 text-zinc-800 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-orange-400" />
        <input wire:model="neighborhood" type="text" placeholder="Bairro"
               class="w-full bg-zinc-50 border border-zinc-200 text-sm rounded-xl px-3 py-2.5 text-zinc-800 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-orange-400" />
    </div>
    <div class="grid grid-cols-5 gap-3">
        <div class="col-span-3">
            <input wire:model="city" type="text" placeholder="Cidade *"
                   class="w-full bg-zinc-50 border border-zinc-200 text-sm rounded-xl px-3 py-2.5 text-zinc-800 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-orange-400" />
            @error('city') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <input wire:model="state" type="text" placeholder="UF *" maxlength="2"
                   class="w-full bg-zinc-50 border border-zinc-200 text-sm rounded-xl px-3 py-2.5 text-zinc-800 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-orange-400 uppercase" />
            @error('state') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
        <div>
            <input wire:model="zip" type="text" placeholder="CEP *"
                   class="w-full bg-zinc-50 border border-zinc-200 text-sm rounded-xl px-3 py-2.5 text-zinc-800 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-orange-400" />
            @error('zip') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
</div>
