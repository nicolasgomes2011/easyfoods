@props(['methods', 'selected'])

{{-- Payment method + optional cash change; the wire: bindings target the parent store.checkout component. --}}
<div class="bg-white border border-zinc-200 rounded-2xl p-5">
    <h2 class="text-sm font-semibold text-zinc-700 mb-3">Forma de pagamento</h2>
    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
        @foreach($methods as $key => $label)
        <label class="cursor-pointer">
            <input type="radio" wire:model.live="paymentMethod" value="{{ $key }}" class="sr-only peer" />
            <div class="border-2 rounded-xl px-2.5 py-2 text-sm text-center transition peer-checked:border-orange-500 peer-checked:bg-orange-50 peer-checked:text-orange-600 border-zinc-200 text-zinc-600">
                {{ $label }}
            </div>
        </label>
        @endforeach
    </div>
    @error('paymentMethod') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror

    @if($selected === \App\Enums\PaymentMethod::Cash->value)
    <div class="mt-4">
        <label for="cash-change-for" class="block text-sm text-zinc-600 mb-1">Troco para quanto? <span class="text-zinc-400">(opcional)</span></label>
        <input id="cash-change-for" wire:model="cashChangeFor" type="text" inputmode="decimal" placeholder="Ex.: 100,00"
               class="w-full sm:w-48 bg-zinc-50 border border-zinc-200 text-sm rounded-xl px-3 py-2.5 text-zinc-800 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-orange-400" />
        @error('cashChangeFor') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
    </div>
    @endif
</div>
