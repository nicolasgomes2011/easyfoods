@props(['table', 'kiosk' => false])

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
