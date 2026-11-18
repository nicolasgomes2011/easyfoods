{{--
    Correction form of the waste screen, wired to the waste/index component
    (correctQuantity, saveCorrection, cancelCorrecting). `entry` is the original
    entry with `adjustments_sum_quantity` loaded (withSum).
--}}
@props(['entry'])

<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" wire:click.self="cancelCorrecting">
    <div class="bg-zinc-900 border border-zinc-700 rounded-2xl w-full max-w-sm mx-4 p-6 shadow-xl">
        <h2 class="text-base font-semibold text-white mb-1">Corrigir lançamento</h2>
        <p class="text-sm text-zinc-400 mb-5">
            {{ $entry->ingredient_name }} · {{ $entry->formattedQuantity($entry->effectiveQuantity()) }}
            · registrado às {{ $entry->created_at->format('H:i') }}
        </p>

        <label for="waste-correct-quantity" class="block text-xs font-medium text-zinc-400 mb-1.5">
            Quantidade correta ({{ $entry->unit->symbol() }})
        </label>
        <input id="waste-correct-quantity" wire:model="correctQuantity" type="number" inputmode="decimal" step="0.001" min="0"
               class="w-full bg-zinc-800 border border-zinc-700 text-white text-lg rounded-xl px-3 py-3 focus:outline-none focus:ring-2 focus:ring-orange-500" />
        <p class="text-xs text-zinc-500 mt-1">Use 0 se o lançamento foi feito por engano. O lançamento original continua no histórico; a diferença entra como correção.</p>
        @error('correctQuantity') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror

        <div class="flex gap-2 mt-6">
            <button wire:click="cancelCorrecting"
                    class="flex-1 text-sm text-zinc-300 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 rounded-xl px-4 py-2.5 transition">
                Cancelar
            </button>
            <button wire:click="saveCorrection" wire:loading.attr="disabled"
                    class="flex-1 text-sm text-white bg-orange-500 hover:bg-orange-600 rounded-xl px-4 py-2.5 transition font-medium">
                <span wire:loading.remove wire:target="saveCorrection">Salvar correção</span>
                <span wire:loading wire:target="saveCorrection">Salvando…</span>
            </button>
        </div>
    </div>
</div>
