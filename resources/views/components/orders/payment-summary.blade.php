{{--
    Compact payment line for the kitchen and driver cards: method, paid or not,
    and the change to bring while a cash payment is still to collect.
    Expects `payment` eager loaded on the order list (no query per card).
--}}
@props(['payment'])

@if($payment)
<div data-payment-summary {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-x-2 gap-y-1 text-xs']) }}>
    <span class="text-zinc-300">{{ $payment->method->label() }}</span>
    @if($payment->isPaid())
    <span class="font-medium text-green-400">Pago</span>
    @else
    <span class="font-medium text-yellow-400">A receber</span>
    @if($payment->amount_tendered !== null)
    <span data-change-due class="font-semibold text-amber-300 tabular-nums">Levar de troco: R$ {{ number_format($payment->change_due, 2, ',', '.') }}</span>
    @endif
    @endif
</div>
@endif
