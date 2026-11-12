{{-- Items added to / removed from the order after it was placed (order_item_changes). --}}
@props(['changes'])

@if($changes->isNotEmpty())
<div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
    <div class="px-5 py-4 border-b border-zinc-800">
        <h2 class="text-sm font-semibold text-white">Alterações nos itens</h2>
    </div>
    <div class="px-5 py-4 space-y-3">
        @foreach($changes as $change)
        <div class="flex items-start gap-3">
            <div class="size-2 rounded-full mt-1.5 shrink-0 {{ $change->type === \App\Enums\OrderItemChangeType::Added ? 'bg-yellow-400' : 'bg-red-400' }}"></div>
            <div>
                <p class="text-sm text-white">
                    {{ $change->type->label() }}: {{ $change->quantity }}× {{ $change->displayName() }}
                    <span class="text-zinc-500 tabular-nums">· R$ {{ number_format($change->subtotal, 2, ',', '.') }}</span>
                </p>
                @if($change->reason)
                <p class="text-xs text-zinc-400 italic">Motivo: {{ $change->reason }}</p>
                @endif
                <p class="text-xs text-zinc-500">
                    {{ $change->changed_at->format('d/m/Y H:i') }}
                    @if($change->changedBy) · {{ $change->changedBy->name }} @endif
                </p>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif
