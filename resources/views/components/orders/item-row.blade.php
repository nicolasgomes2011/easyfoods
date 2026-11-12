{{--
    One line of the order screen. With `canRemove`, shows "Remover"; with `removing`,
    the inline reason form (wired to the orders/show component: removeReason,
    removeItem, cancelRemoving). `removeError` is that form's validation message.
--}}
@props(['item', 'canRemove' => false, 'removing' => false, 'removeError' => null])

<div {{ $attributes->merge(['class' => 'px-5 py-3.5']) }}>
    <div class="flex items-start justify-between">
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-white">
                {{ $item->displayName() }}
                <span class="text-zinc-500">×{{ $item->quantity }}</span>
                @if($item->wasAddedLater())
                <span class="ml-1 inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-semibold text-yellow-400 bg-yellow-400/10 border border-yellow-400/20">
                    Adicionado {{ $item->added_at->format('H:i') }}
                </span>
                @endif
            </p>
            @if($item->notes)
            <p class="text-xs text-zinc-500 mt-0.5 italic">{{ $item->notes }}</p>
            @endif
            @foreach($item->addons as $addon)
            {{-- Frozen snapshot (order_item_addons): "Extras: Bacon", or just the option without a group name --}}
            <p class="text-xs text-zinc-500 mt-0.5">+ {{ filled($addon->addon_group_name) ? $addon->addon_group_name.': ' : '' }}{{ $addon->addon_option_name }}</p>
            @endforeach
        </div>
        <div class="flex items-center gap-3 ml-4 shrink-0">
            <span class="text-sm text-zinc-200 tabular-nums">
                R$ {{ number_format($item->subtotal, 2, ',', '.') }}
            </span>
            @if($canRemove && ! $removing)
            <button
                wire:click="startRemoving({{ $item->id }})"
                class="text-xs text-red-400 hover:text-red-300 transition">
                Remover
            </button>
            @endif
        </div>
    </div>

    @if($removing)
    <div class="mt-3 rounded-lg border border-red-500/30 bg-red-500/5 p-3">
        <label class="block text-xs text-zinc-400 mb-1.5">Motivo da remoção (obrigatório)</label>
        <input
            type="text"
            wire:model="removeReason"
            placeholder="Ex: cliente desistiu, item em falta..."
            class="w-full bg-zinc-900 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-red-500" />
        @if($removeError)
        <p class="text-xs text-red-400 mt-1.5">{{ $removeError }}</p>
        @endif
        <div class="flex items-center gap-2 mt-2.5">
            <button
                wire:click="removeItem"
                wire:loading.attr="disabled"
                wire:target="removeItem"
                class="inline-flex items-center text-xs font-medium text-white bg-red-600 hover:bg-red-500 rounded-lg px-3 py-1.5 transition disabled:opacity-50">
                Remover item
            </button>
            <button
                type="button"
                wire:click="cancelRemoving"
                class="text-xs text-zinc-400 hover:text-white px-2 py-1.5 transition">
                Voltar
            </button>
        </div>
    </div>
    @endif
</div>
