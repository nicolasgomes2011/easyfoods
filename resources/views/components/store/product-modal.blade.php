@props(['product', 'error' => null, 'selectedVariant' => null, 'qty' => 1, 'unitPrice' => 0.0])

{{-- Product quick-add modal; the wire: bindings target the parent store.menu component. --}}
<div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50" wire:click.self="closeProduct">
    <div class="bg-white rounded-t-3xl sm:rounded-2xl w-full max-w-md p-6 shadow-xl max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-bold text-zinc-800">{{ $product->name }}</h3>
        @if($product->description)
        <p class="text-sm text-zinc-500 mt-1">{{ $product->description }}</p>
        @endif

        @if($error)
        <div class="mt-3 px-3 py-2 bg-red-50 border border-red-200 text-red-600 text-xs rounded-xl">
            {{ $error }}
        </div>
        @endif

        <div class="mt-4 space-y-4">

            {{-- Variations (required single choice) --}}
            @if($product->variants->isNotEmpty())
            <div>
                <label class="block text-xs font-semibold text-zinc-600 mb-2">Opção <span class="text-red-400">*</span></label>
                <div class="space-y-2">
                    @foreach($product->variants as $variant)
                    <label class="flex items-center justify-between cursor-pointer border-2 rounded-xl px-3 py-2.5 transition
                                  {{ $selectedVariant === $variant->id ? 'border-orange-500 bg-orange-50' : 'border-zinc-200 hover:border-zinc-300' }}">
                        <span class="flex items-center gap-2 text-sm text-zinc-700">
                            <input type="radio" wire:model.live="selectedVariant" value="{{ $variant->id }}" class="accent-orange-500" />
                            {{ $variant->name }}
                        </span>
                        <span class="text-sm font-semibold text-zinc-700 tabular-nums">R$ {{ number_format($variant->price, 2, ',', '.') }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Addon groups --}}
            @foreach($product->addonGroups as $group)
            @if($group->activeOptions->isNotEmpty())
            <div>
                <label class="block text-xs font-semibold text-zinc-600 mb-0.5">
                    {{ $group->name }}
                    @if($group->required)<span class="text-red-400">*</span>@endif
                </label>
                <p class="text-[11px] text-zinc-400 mb-2">
                    @if($group->max_choices > 1)Escolha até {{ $group->max_choices }}@else Escolha 1 @endif
                </p>
                <div class="space-y-2">
                    @foreach($group->activeOptions as $option)
                    <label class="flex items-center justify-between cursor-pointer border rounded-xl px-3 py-2 transition border-zinc-200 hover:border-zinc-300">
                        <span class="flex items-center gap-2 text-sm text-zinc-700">
                            @if($group->max_choices > 1)
                            <input type="checkbox" wire:model.live="selectedAddons.{{ $group->id }}" value="{{ $option->id }}" class="accent-orange-500" />
                            @else
                            <input type="radio" wire:model.live="selectedAddons.{{ $group->id }}" value="{{ $option->id }}" class="accent-orange-500" />
                            @endif
                            {{ $option->name }}
                        </span>
                        @if((float) $option->price > 0)
                        <span class="text-xs text-zinc-500 tabular-nums">+ R$ {{ number_format($option->price, 2, ',', '.') }}</span>
                        @endif
                    </label>
                    @endforeach
                </div>
            </div>
            @endif
            @endforeach

            {{-- Notes --}}
            <div>
                <label class="block text-xs font-medium text-zinc-500 mb-1.5">Observações</label>
                <input wire:model="notes" type="text" placeholder="Ex: sem cebola, bem passado…"
                       class="w-full bg-zinc-50 border border-zinc-200 text-zinc-800 placeholder-zinc-400 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-400" />
            </div>

            {{-- Quantity --}}
            <div class="flex items-center justify-between">
                <label class="text-xs font-medium text-zinc-500">Quantidade</label>
                <div class="flex items-center gap-3">
                    <button type="button" wire:click="$set('qty', {{ max(1, $qty - 1) }})"
                            class="size-8 flex items-center justify-center rounded-full border border-zinc-300 text-zinc-600 hover:bg-zinc-100 transition">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" /></svg>
                    </button>
                    <span class="text-sm font-semibold text-zinc-800 w-4 text-center">{{ $qty }}</span>
                    <button type="button" wire:click="$set('qty', {{ $qty + 1 }})"
                            class="size-8 flex items-center justify-center rounded-full border border-zinc-300 text-zinc-600 hover:bg-zinc-100 transition">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    </button>
                </div>
            </div>
        </div>

        <div class="flex gap-3 mt-6">
            <button type="button" wire:click="closeProduct"
                    class="flex-1 py-3 rounded-xl border border-zinc-200 text-sm text-zinc-600 hover:bg-zinc-50 transition">
                Cancelar
            </button>
            <button type="button" wire:click="addToCart({{ $product->id }})" wire:loading.attr="disabled"
                    class="flex-1 py-3 rounded-xl bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold transition">
                <span wire:loading.remove wire:target="addToCart">Adicionar • R$ {{ number_format($unitPrice * $qty, 2, ',', '.') }}</span>
                <span wire:loading wire:target="addToCart">Adicionando…</span>
            </button>
        </div>
    </div>
</div>
