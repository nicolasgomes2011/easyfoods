@props(['order'])

{{-- How the order leaves the kitchen. A dine-in order names its table so staff know where to take it. --}}
@if($order->delivery_type === \App\Enums\DeliveryType::DineIn && $order->table_number)
<span {{ $attributes->merge(['class' => 'font-semibold text-orange-400']) }}>Mesa {{ $order->table_number }}</span>
@else
<span {{ $attributes }}>{{ $order->delivery_type?->label() }}</span>
@endif
