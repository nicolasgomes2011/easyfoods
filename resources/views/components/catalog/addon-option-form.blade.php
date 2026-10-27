@props(['editing' => false])

{{-- Addon option create/edit modal; the wire: bindings target the parent catalog.addons component. --}}
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" wire:click.self="cancelOptionForm">
    <div class="bg-zinc-900 border border-zinc-700 rounded-2xl w-full max-w-xs mx-4 p-6 shadow-xl">
        <h2 class="text-base font-semibold text-white mb-5">
            {{ $editing ? 'Editar opção' : 'Nova opção' }}
        </h2>

        <div class="space-y-4">
            <div>
                <label class="block text-xs font-medium text-zinc-400 mb-1.5">Nome *</label>
                <input wire:model="optionName" type="text" placeholder="Ex: Mal passado, Queijo extra…"
                       class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                @error('optionName') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-zinc-400 mb-1.5">Preço adicional (R$)</label>
                <input wire:model="optionPrice" type="number" step="0.01" min="0" placeholder="0.00"
                       class="w-full bg-zinc-800 border border-zinc-700 text-white text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                @error('optionPrice') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex gap-2 mt-6">
            <button wire:click="cancelOptionForm"
                    class="flex-1 text-sm text-zinc-300 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 rounded-xl px-4 py-2.5 transition">
                Cancelar
            </button>
            <button wire:click="saveOption" wire:loading.attr="disabled"
                    class="flex-1 text-sm text-white bg-orange-500 hover:bg-orange-600 rounded-xl px-4 py-2.5 transition font-medium">
                <span wire:loading.remove wire:target="saveOption">{{ $editing ? 'Salvar' : 'Adicionar' }}</span>
                <span wire:loading wire:target="saveOption">Salvando…</span>
            </button>
        </div>
    </div>
</div>
