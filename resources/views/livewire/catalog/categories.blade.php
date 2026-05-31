<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use App\Models\Category;
use App\Models\Restaurant;
use Illuminate\Support\Str;

new #[Layout('components.layouts.app')] class extends Component {

    public bool   $showForm   = false;
    public string $editingId  = '';
    public string $name        = '';
    public string $description = '';
    public bool   $isActive    = true;

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'isActive'    => 'boolean',
        ];
    }

    #[Computed]
    public function categories()
    {
        return Category::where('restaurant_id', Restaurant::query()->value('id'))
            ->withCount('products')->orderBy('sort_order')->orderBy('name')->get();
    }

    public function openCreate(): void
    {
        $this->reset('name', 'description', 'editingId');
        $this->isActive = true;
        $this->showForm = true;
    }

    public function openEdit(Category $category): void
    {
        $this->editingId   = (string) $category->id;
        $this->name        = $category->name;
        $this->description = $category->description ?? '';
        $this->isActive    = $category->is_active;
        $this->showForm    = true;
    }

    public function save(): void
    {
        $this->validate();

        if ($this->editingId) {
            Category::findOrFail($this->editingId)->update([
                'name'        => $this->name,
                'slug'        => Str::slug($this->name),
                'description' => $this->description ?: null,
                'is_active'   => $this->isActive,
            ]);
        } else {
            Category::create([
                'restaurant_id' => Restaurant::query()->value('id'),
                'name'          => $this->name,
                'slug'          => Str::slug($this->name),
                'description'   => $this->description ?: null,
                'is_active'     => $this->isActive,
                'sort_order'    => Category::max('sort_order') + 1,
            ]);
        }

        $this->showForm = false;
        $this->reset('name', 'description', 'editingId');
        $this->isActive = true;
        unset($this->categories);
    }

    public function toggleActive(Category $category): void
    {
        $category->update(['is_active' => ! $category->is_active]);
        unset($this->categories);
    }

    public function delete(Category $category): void
    {
        if ($category->products()->exists()) {
            $this->addError('delete', "Não é possível excluir '{$category->name}': há produtos vinculados.");
            return;
        }

        $category->delete();
        unset($this->categories);
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->reset('name', 'description', 'editingId');
        $this->isActive = true;
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Categorias</h1>
            <p class="text-sm text-zinc-400 mt-0.5">{{ $this->categories->count() }} categoria(s) no cardápio</p>
        </div>
        <button wire:click="openCreate"
                class="text-sm text-white bg-orange-500 hover:bg-orange-600 rounded-lg px-3 py-1.5 transition">
            + Nova categoria
        </button>
    </div>

    @error('delete')
    <div class="mb-4 px-4 py-3 bg-red-500/10 border border-red-500/20 rounded-xl text-sm text-red-400">
        {{ $message }}
    </div>
    @enderror

    {{-- Modal form --}}
    @if($showForm)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" wire:click.self="cancelForm">
        <div class="bg-zinc-900 border border-zinc-700 rounded-2xl w-full max-w-sm mx-4 p-6 shadow-xl">
            <h2 class="text-base font-semibold text-white mb-5">
                {{ $editingId ? 'Editar categoria' : 'Nova categoria' }}
            </h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">Nome *</label>
                    <input
                        wire:model="name"
                        type="text"
                        placeholder="Ex: Hambúrgueres, Bebidas, Sobremesas…"
                        class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500"
                    />
                    @error('name') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">Descrição</label>
                    <input
                        wire:model="description"
                        type="text"
                        placeholder="Opcional"
                        class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500"
                    />
                    @error('description') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-center gap-2.5 cursor-pointer select-none">
                    <input type="checkbox" wire:model="isActive" class="rounded border-zinc-600 bg-zinc-800 text-orange-500 focus:ring-orange-500" />
                    <span class="text-sm text-zinc-300">Categoria ativa</span>
                </label>
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
    @if($this->categories->isEmpty())
    <div class="bg-zinc-900 border border-zinc-800 border-dashed rounded-xl px-5 py-16 text-center">
        <p class="text-zinc-500 text-sm mb-3">Nenhuma categoria cadastrada ainda.</p>
        <button wire:click="openCreate"
                class="text-sm text-orange-400 hover:text-orange-300 transition">
            + Criar primeira categoria
        </button>
    </div>
    @else
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-zinc-800">
                    <th class="text-left px-5 py-3 text-xs font-medium text-zinc-500">Categoria</th>
                    <th class="text-center px-4 py-3 text-xs font-medium text-zinc-500">Produtos</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-zinc-500">Status</th>
                    <th class="text-right px-5 py-3 text-xs font-medium text-zinc-500">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800/70">
                @foreach($this->categories as $category)
                <tr class="hover:bg-zinc-800/30 transition">
                    <td class="px-5 py-3.5">
                        <p class="font-medium text-white">{{ $category->name }}</p>
                        @if($category->description)
                        <p class="text-xs text-zinc-500 mt-0.5">{{ $category->description }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3.5 text-center">
                        <span class="text-zinc-400 tabular-nums">{{ $category->products_count }}</span>
                    </td>
                    <td class="px-4 py-3.5">
                        @if($category->is_active)
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-400/10 text-green-400">
                            Ativa
                        </span>
                        @else
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-zinc-700 text-zinc-400">
                            Inativa
                        </span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center justify-end gap-1">
                            <button wire:click="toggleActive({{ $category->id }})"
                                    class="text-xs text-zinc-400 hover:text-white bg-zinc-800 hover:bg-zinc-700 rounded-lg px-2.5 py-1.5 transition">
                                {{ $category->is_active ? 'Desativar' : 'Ativar' }}
                            </button>
                            <button wire:click="openEdit({{ $category->id }})"
                                    class="text-xs text-zinc-400 hover:text-white bg-zinc-800 hover:bg-zinc-700 rounded-lg px-2.5 py-1.5 transition">
                                Editar
                            </button>
                            <button wire:click="delete({{ $category->id }})"
                                    wire:confirm="Excluir a categoria '{{ $category->name }}'?"
                                    class="text-xs text-red-400/70 hover:text-red-400 bg-zinc-800 hover:bg-red-400/10 rounded-lg px-2.5 py-1.5 transition">
                                Excluir
                            </button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
