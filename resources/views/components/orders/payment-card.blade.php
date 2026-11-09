@props(['order', 'payment'])

{{-- Payment block of the order page; the wire:click targets the parent orders.show component. --}}
<div class="bg-zinc-900 border border-zinc-800 rounded-xl p-5">
    <h2 class="text-sm font-semibold text-white mb-4">Pagamento</h2>
    <div class="space-y-2.5 text-sm">
        <div class="flex items-center justify-between">
            <span class="text-zinc-500">Método</span>
            <span class="text-zinc-300">{{ $payment->method->label() }}</span>
        </div>
        <div class="flex items-center justify-between">
            <span class="text-zinc-500">Status</span>
            @if($payment->isPaid())
            <span class="text-green-400 text-xs font-medium">Pago</span>
            @else
            <span class="text-yellow-400 text-xs font-medium">Pendente</span>
            @endif
        </div>
        <div class="flex items-center justify-between">
            <span class="text-zinc-500">Valor</span>
            <span class="text-zinc-200 tabular-nums">R$ {{ number_format($payment->amount, 2, ',', '.') }}</span>
        </div>
        @if($payment->amount_tendered !== null)
        <div class="flex items-center justify-between">
            <span class="text-zinc-500">Troco para</span>
            <span class="text-zinc-200 tabular-nums">R$ {{ number_format($payment->amount_tendered, 2, ',', '.') }}</span>
        </div>
        <div class="flex items-center justify-between">
            <span class="text-zinc-500">Levar de troco</span>
            <span class="text-amber-300 tabular-nums font-medium">R$ {{ number_format($payment->change_due, 2, ',', '.') }}</span>
        </div>
        @endif
        @if($payment->paid_at)
        <div class="flex items-center justify-between">
            <span class="text-zinc-500">Pago em</span>
            <span class="text-zinc-400 text-xs">{{ $payment->paid_at->format('d/m/Y H:i') }}</span>
        </div>
        @endif
    </div>

    @can('markPaid', $order)
        @if($payment->status->canTransitionTo(\App\Enums\PaymentStatus::Paid))
        <button
            wire:click="markAsPaid"
            wire:loading.attr="disabled"
            wire:target="markAsPaid"
            class="mt-4 w-full inline-flex items-center justify-center gap-1.5 text-sm font-medium text-green-400 bg-green-400/10 hover:bg-green-400/15 border border-green-400/20 rounded-lg px-3.5 py-2 transition disabled:opacity-50">
            Marcar como pago
        </button>
        @endif
    @endcan
</div>
