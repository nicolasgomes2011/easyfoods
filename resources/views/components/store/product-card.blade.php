@props(['product'])

<div class="bg-white border border-zinc-200 rounded-2xl overflow-hidden hover:shadow-md transition group">
    {{-- Image placeholder --}}
    <div class="h-36 bg-zinc-100 flex items-center justify-center">
        @if($product->image)
        <img src="{{ $product->image }}" alt="{{ $product->name }}" class="w-full h-full object-cover" />
        @else
        <svg class="size-12 text-zinc-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8.25v-1.5m0 1.5c-1.355 0-2.697.056-4.024.166C6.845 8.51 6 9.473 6 10.608v2.513m6-4.871c1.355 0 2.697.056 4.024.166C17.155 8.51 18 9.473 18 10.608v2.513M15 8.25v-1.5m-6 1.5v-1.5m12 9.75-1.5.75a3.354 3.354 0 0 1-3 0 3.354 3.354 0 0 1-3 0 3.354 3.354 0 0 1-3 0 3.354 3.354 0 0 1-3 0l-1.5-.75M8.25 19.5l1.5-7.5L12 13.5l2.25-1.5 1.5 7.5" /></svg>
        @endif
    </div>

    <div class="p-4">
        <div class="flex items-start justify-between gap-2">
            <div class="flex-1 min-w-0">
                <h3 class="font-semibold text-zinc-800 truncate">{{ $product->name }}</h3>
                @if($product->description)
                <p class="text-xs text-zinc-500 mt-0.5 line-clamp-2">{{ $product->description }}</p>
                @endif
            </div>
            @if($product->is_featured)
            <span class="shrink-0 text-[10px] font-semibold text-orange-500 bg-orange-50 rounded-full px-1.5 py-0.5">destaque</span>
            @endif
        </div>

        <div class="flex items-center justify-between mt-3">
            <span class="font-bold text-zinc-800">R$ {{ number_format($product->price, 2, ',', '.') }}</span>
            <button wire:click="openProduct({{ $product->id }})"
                    class="flex items-center gap-1 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-xl px-3 py-1.5 transition">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                Adicionar
            </button>
        </div>
    </div>
</div>
