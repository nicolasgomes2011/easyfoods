<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use App\Enums\IngredientCategory;
use App\Enums\IngredientUnit;
use App\Models\Ingredient;
use App\Support\CurrentRestaurant;
use Illuminate\Validation\Rule;

/*
 * Ingredients (insumos) of the restaurant: name, unit, cost per unit and
 * category. Archived instead of deleted, so later records can keep pointing
 * at them. Every lookup is fenced by restaurant_id: another restaurant's
 * ingredient is a 404.
 */
new #[Layout('components.layouts.app')] class extends Component {

    public bool   $showForm     = false;
    public bool   $showArchived = false;
    public string $editingId    = '';
    public string $name         = '';
    public string $unit         = 'kg';
    public string $category     = 'other';
    public string $cost         = '';

    public function rules(): array
    {
        return [
            'name'     => [
                'required', 'string', 'max:100',
                Rule::unique('ingredients', 'name')
                    ->where('restaurant_id', $this->rid())
                    ->ignore($this->editingId ? (int) $this->editingId : null),
            ],
            'unit'     => ['required', Rule::enum(IngredientUnit::class)],
            'category' => ['required', Rule::enum(IngredientCategory::class)],
            'cost'     => ['required', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'name'     => 'nome',
            'unit'     => 'unidade',
            'category' => 'categoria',
            'cost'     => 'custo por unidade',
        ];
    }

    private function rid(): ?int
    {
        return CurrentRestaurant::id();
    }

    /** Scoped lookup: implicit binding would resolve another restaurant's ingredient (IDOR). */
    private function scoped(int $ingredientId): Ingredient
    {
        return Ingredient::where('restaurant_id', $this->rid())->findOrFail($ingredientId);
    }

    #[Computed]
    public function ingredients()
    {
        return Ingredient::where('restaurant_id', $this->rid())
            ->when(! $this->showArchived, fn ($query) => $query->whereNull('archived_at'))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function archivedCount(): int
    {
        return Ingredient::where('restaurant_id', $this->rid())->whereNotNull('archived_at')->count();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function openEdit(int $ingredientId): void
    {
        $ingredient = $this->scoped($ingredientId);

        $this->resetValidation();
        $this->editingId = (string) $ingredient->id;
        $this->name      = $ingredient->name;
        $this->unit      = $ingredient->unit->value;
        $this->category  = $ingredient->category->value;
        $this->cost      = (string) (float) $ingredient->cost_per_unit;
        $this->showForm  = true;
    }

    public function save(): void
    {
        // Before validating: a foreign id is a 404, never an update.
        $ingredient = $this->editingId ? $this->scoped((int) $this->editingId) : null;

        $this->name = trim($this->name);
        $this->validate();

        $attributes = [
            'name'          => $this->name,
            'unit'          => $this->unit,
            'category'      => $this->category,
            'cost_per_unit' => $this->cost,
        ];

        if ($ingredient) {
            $ingredient->update($attributes);
        } else {
            Ingredient::create($attributes + ['restaurant_id' => $this->rid()]);
        }

        $this->cancelForm();
    }

    public function archive(int $ingredientId): void
    {
        $this->scoped($ingredientId)->update(['archived_at' => now()]);
        unset($this->ingredients, $this->archivedCount);
    }

    public function restore(int $ingredientId): void
    {
        $this->scoped($ingredientId)->update(['archived_at' => null]);
        unset($this->ingredients, $this->archivedCount);
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
        unset($this->ingredients);
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'name', 'unit', 'category', 'cost');
    }
}; ?>

<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Insumos</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Ingredientes que o restaurante compra, com o custo por unidade</p>
        </div>
        <div class="flex items-center gap-3">
            @if($this->archivedCount > 0)
            <label class="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" wire:model.live="showArchived" class="rounded border-zinc-600 bg-zinc-800 text-orange-500 focus:ring-orange-500" />
                <span class="text-xs text-zinc-400">Mostrar arquivados ({{ $this->archivedCount }})</span>
            </label>
            @endif
            <button wire:click="openCreate"
                    class="text-sm text-white bg-orange-500 hover:bg-orange-600 rounded-lg px-3 py-1.5 transition">
                + Novo insumo
            </button>
        </div>
    </div>

    {{-- Modal form --}}
    @if($showForm)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" wire:click.self="cancelForm">
        <div class="bg-zinc-900 border border-zinc-700 rounded-2xl w-full max-w-sm mx-4 p-6 shadow-xl">
            <h2 class="text-base font-semibold text-white mb-5">
                {{ $editingId ? 'Editar insumo' : 'Novo insumo' }}
            </h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">Nome *</label>
                    <input wire:model="name" type="text" maxlength="100" placeholder="Ex: Queijo muçarela, Tomate, Pão de hambúrguer…"
                           class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                    @error('name') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-zinc-400 mb-1.5">Unidade *</label>
                        <select wire:model="unit"
                                class="w-full bg-zinc-800 border border-zinc-700 text-white text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500">
                            @foreach(IngredientUnit::cases() as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                        @error('unit') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-zinc-400 mb-1.5">Categoria *</label>
                        <select wire:model="category"
                                class="w-full bg-zinc-800 border border-zinc-700 text-white text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500">
                            @foreach(IngredientCategory::cases() as $option)
                            <option value="{{ $option->value }}">{{ $option->label() }}</option>
                            @endforeach
                        </select>
                        @error('category') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">Custo por unidade (R$) *</label>
                    <input wire:model="cost" type="number" step="0.0001" min="0" placeholder="0.00"
                           class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                    <p class="text-xs text-zinc-500 mt-1">Quanto custa 1 unidade escolhida acima (ex.: 1 kg).</p>
                    @error('cost') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex gap-2 mt-6">
                <button wire:click="cancelForm"
                        class="flex-1 text-sm text-zinc-300 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 rounded-xl px-4 py-2.5 transition">
                    Cancelar
                </button>
                <button wire:click="save" wire:loading.attr="disabled"
                        class="flex-1 text-sm text-white bg-orange-500 hover:bg-orange-600 rounded-xl px-4 py-2.5 transition font-medium">
                    <span wire:loading.remove wire:target="save">{{ $editingId ? 'Salvar' : 'Adicionar' }}</span>
                    <span wire:loading wire:target="save">Salvando…</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- List --}}
    @if($this->ingredients->isEmpty())
    <div class="bg-zinc-900 border border-zinc-800 border-dashed rounded-xl px-5 py-16 text-center">
        <p class="text-zinc-500 text-sm mb-3">Nenhum insumo cadastrado ainda.</p>
        <button wire:click="openCreate" class="text-sm text-orange-400 hover:text-orange-300 transition">
            + Cadastrar primeiro insumo
        </button>
    </div>
    @else
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-zinc-800">
                    <th class="text-left px-5 py-3 text-xs font-medium text-zinc-500">Insumo</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-zinc-500">Categoria</th>
                    <th class="text-right px-4 py-3 text-xs font-medium text-zinc-500">Custo</th>
                    <th class="text-right px-5 py-3 text-xs font-medium text-zinc-500">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800/70">
                @foreach($this->ingredients as $ingredient)
                <tr wire:key="ingredient-{{ $ingredient->id }}" class="hover:bg-zinc-800/30 transition {{ $ingredient->isArchived() ? 'opacity-60' : '' }}">
                    <td class="px-5 py-3.5">
                        <p class="font-medium text-white">{{ $ingredient->name }}</p>
                        @if($ingredient->isArchived())
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 mt-1 text-xs font-medium bg-zinc-700 text-zinc-400">Arquivado</span>
                        @endif
                    </td>
                    <td class="px-4 py-3.5 text-zinc-300">{{ $ingredient->category->label() }}</td>
                    <td class="px-4 py-3.5 text-right text-zinc-200 tabular-nums whitespace-nowrap">
                        R$ {{ $ingredient->formattedCost() }} / {{ $ingredient->unit->symbol() }}
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center justify-end gap-1">
                            @if($ingredient->isArchived())
                            <button wire:click="restore({{ $ingredient->id }})"
                                    class="text-xs text-green-400/80 hover:text-green-400 bg-zinc-800 hover:bg-green-400/10 rounded-lg px-2.5 py-1.5 transition">
                                Reativar
                            </button>
                            @else
                            <button wire:click="openEdit({{ $ingredient->id }})"
                                    class="text-xs text-zinc-400 hover:text-white bg-zinc-800 hover:bg-zinc-700 rounded-lg px-2.5 py-1.5 transition">
                                Editar
                            </button>
                            <button wire:click="archive({{ $ingredient->id }})"
                                    wire:confirm="Arquivar o insumo '{{ $ingredient->name }}'? Ele sai da lista, mas pode ser reativado."
                                    class="text-xs text-red-400/70 hover:text-red-400 bg-zinc-800 hover:bg-red-400/10 rounded-lg px-2.5 py-1.5 transition">
                                Arquivar
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
