@props(['products', 'editing' => false])

{{-- Addon group create/edit modal; the wire: bindings target the parent catalog.addons component. --}}
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" wire:click.self="cancelGroupForm">
    <div class="bg-zinc-900 border border-zinc-700 rounded-2xl w-full max-w-sm mx-4 p-6 shadow-xl">
        <h2 class="text-base font-semibold text-white mb-5">
            {{ $editing ? 'Editar grupo' : 'Novo grupo de complementos' }}
        </h2>

        <div class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-zinc-400 mb-1.5">Produto *</label>
                <select wire:model="groupProductId"
                        class="w-full bg-zinc-800 border border-zinc-700 text-zinc-300 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500">
                    <option value="">Selecionar produto…</option>
                    @foreach($products as $product)
                    <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
                @error('groupProductId') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-zinc-400 mb-1.5">Nome do grupo *</label>
                <input wire:model="groupName" type="text" placeholder="Ex: Ponto da carne, Adicionais…"
                       class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                @error('groupName') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">Mínimo de escolhas</label>
                    <input wire:model="groupMinChoices" type="number" min="0"
                           class="w-full bg-zinc-800 border border-zinc-700 text-white text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">Máximo de escolhas</label>
                    <input wire:model="groupMaxChoices" type="number" min="1"
                           class="w-full bg-zinc-800 border border-zinc-700 text-white text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                </div>
            </div>

            <label class="flex items-center gap-2.5 cursor-pointer select-none">
                <input type="checkbox" wire:model="groupRequired"
                       class="rounded border-zinc-600 bg-zinc-800 text-orange-500 focus:ring-orange-500" />
                <span class="text-sm text-zinc-300">Seleção obrigatória</span>
            </label>
        </div>

        <div class="flex gap-2 mt-6">
            <button wire:click="cancelGroupForm"
                    class="flex-1 text-sm text-zinc-300 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 rounded-xl px-4 py-2.5 transition">
                Cancelar
            </button>
            <button wire:click="saveGroup" wire:loading.attr="disabled"
                    class="flex-1 text-sm text-white bg-orange-500 hover:bg-orange-600 rounded-xl px-4 py-2.5 transition font-medium">
                <span wire:loading.remove wire:target="saveGroup">{{ $editing ? 'Salvar' : 'Criar' }}</span>
                <span wire:loading wire:target="saveGroup">Salvando…</span>
            </button>
        </div>
    </div>
</div>
