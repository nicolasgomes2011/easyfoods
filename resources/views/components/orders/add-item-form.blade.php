{{--
    "Adicionar item" form of the order screen, wired to the orders/show component
    (newProductId, newVariantId, newAddons, newQuantity, newNotes, addItem, closeAddItem).
    `products` are the orderable products, `selected` the chosen one (variants and
    active addon groups loaded), `messages` the errors by field (newItem = refusal).
--}}
@props(['products', 'selected' => null, 'messages' => []])

<div class="px-5 py-4 border-t border-zinc-800 bg-zinc-950/40 space-y-3">
    <h3 class="text-xs font-semibold text-zinc-300 uppercase tracking-wider">Adicionar item</h3>

    <div>
        <label class="block text-xs text-zinc-400 mb-1.5">Produto</label>
        <select
            wire:model.live="newProductId"
            class="w-full bg-zinc-900 border border-zinc-700 text-white text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-500">
            <option value="">Escolha um produto</option>
            @foreach($products as $product)
            <option value="{{ $product->id }}">{{ $product->name }} — R$ {{ number_format($product->price, 2, ',', '.') }}</option>
            @endforeach
        </select>
        @if($messages['newProductId'] ?? null)
        <p class="text-xs text-red-400 mt-1.5">{{ $messages['newProductId'] }}</p>
        @endif
    </div>

    @if($selected)
        @if($selected->variants->isNotEmpty())
        <div>
            <label class="block text-xs text-zinc-400 mb-1.5">Opção</label>
            <select
                wire:model="newVariantId"
                class="w-full bg-zinc-900 border border-zinc-700 text-white text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-500">
                <option value="">Escolha uma opção</option>
                @foreach($selected->variants as $variant)
                <option value="{{ $variant->id }}">{{ $variant->name }} — R$ {{ number_format($variant->price, 2, ',', '.') }}</option>
                @endforeach
            </select>
        </div>
        @endif

        @foreach($selected->addonGroups as $group)
        @continue($group->activeOptions->isEmpty())
        <div wire:key="add-group-{{ $group->id }}">
            <p class="text-xs text-zinc-400 mb-1.5">
                {{ $group->name }}
                @if($group->required)<span class="text-orange-400">· obrigatório</span>@endif
                @if($group->allowsMultiple())<span class="text-zinc-500">· até {{ $group->max_choices }}</span>@endif
            </p>
            <div class="flex flex-wrap gap-2">
                @foreach($group->activeOptions as $option)
                <label class="inline-flex items-center gap-1.5 text-xs text-zinc-300 bg-zinc-900 border border-zinc-700 rounded-lg px-2.5 py-1.5 cursor-pointer">
                    <input type="{{ $group->allowsMultiple() ? 'checkbox' : 'radio' }}" value="{{ $option->id }}"
                           wire:model="newAddons.{{ $group->id }}" class="accent-orange-500" />
                    {{ $option->name }}
                    @unless($option->isFree())
                    <span class="text-zinc-500">+ R$ {{ number_format($option->price, 2, ',', '.') }}</span>
                    @endunless
                </label>
                @endforeach
            </div>
        </div>
        @endforeach
    @endif

    <div class="grid grid-cols-3 gap-3">
        <div>
            <label class="block text-xs text-zinc-400 mb-1.5">Quantidade</label>
            <input
                type="number" min="1" max="{{ \App\Actions\Orders\AddOrderItem::MAX_QUANTITY }}"
                wire:model="newQuantity"
                class="w-full bg-zinc-900 border border-zinc-700 text-white text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-500" />
            @if($messages['newQuantity'] ?? null)
            <p class="text-xs text-red-400 mt-1.5">{{ $messages['newQuantity'] }}</p>
            @endif
        </div>
        <div class="col-span-2">
            <label class="block text-xs text-zinc-400 mb-1.5">Observação</label>
            <input
                type="text"
                wire:model="newNotes"
                placeholder="Ex: sem gelo"
                class="w-full bg-zinc-900 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-500" />
            @if($messages['newNotes'] ?? null)
            <p class="text-xs text-red-400 mt-1.5">{{ $messages['newNotes'] }}</p>
            @endif
        </div>
    </div>

    @if($messages['newItem'] ?? null)
    <p class="text-xs text-red-400">{{ $messages['newItem'] }}</p>
    @endif

    <div class="flex items-center gap-2">
        <button
            wire:click="addItem"
            wire:loading.attr="disabled"
            wire:target="addItem"
            class="inline-flex items-center text-xs font-medium text-white bg-orange-600 hover:bg-orange-500 rounded-lg px-3 py-1.5 transition disabled:opacity-50">
            Adicionar ao pedido
        </button>
        <button
            type="button"
            wire:click="closeAddItem"
            class="text-xs text-zinc-400 hover:text-white px-2 py-1.5 transition">
            Fechar
        </button>
    </div>
</div>
