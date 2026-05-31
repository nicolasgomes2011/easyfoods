<?php
use App\Models\AddonGroup;
use App\Models\AddonOption;
use App\Models\Product;
use App\Models\Restaurant;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {

    // Group form
    public bool   $showGroupForm  = false;
    public string $editingGroupId = '';
    public string $groupProductId = '';
    public string $groupName      = '';
    public bool   $groupRequired  = false;
    public int    $groupMinChoices = 0;
    public int    $groupMaxChoices = 1;

    // Option form
    public bool   $showOptionForm  = false;
    public string $editingOptionId = '';
    public string $optionGroupId   = '';
    public string $optionName      = '';
    public string $optionPrice     = '0.00';

    public ?string $deleteError = null;

    public function groupRules(): array
    {
        return [
            'groupName'       => 'required|string|max:100',
            'groupProductId'  => 'required|exists:products,id',
            'groupRequired'   => 'boolean',
            'groupMinChoices' => 'required|integer|min:0',
            'groupMaxChoices' => 'required|integer|min:1',
        ];
    }

    public function optionRules(): array
    {
        return [
            'optionName'  => 'required|string|max:100',
            'optionPrice' => 'required|numeric|min:0',
        ];
    }

    #[Computed]
    public function restaurant(): ?Restaurant
    {
        return Restaurant::first();
    }

    #[Computed]
    public function products()
    {
        if (! $this->restaurant) return collect();
        return Product::where('restaurant_id', $this->restaurant->id)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function groups()
    {
        if (! $this->restaurant) return collect();
        return AddonGroup::whereHas('product', fn ($q) => $q->where('restaurant_id', $this->restaurant->id))
            ->with(['product:id,name', 'options'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    // ── Group actions ──────────────────────────────────────────────────────

    public function openCreateGroup(): void
    {
        $this->reset('editingGroupId', 'groupName', 'groupProductId');
        $this->groupRequired   = false;
        $this->groupMinChoices = 0;
        $this->groupMaxChoices = 1;
        $this->showGroupForm   = true;
    }

    public function openEditGroup(AddonGroup $group): void
    {
        $this->editingGroupId  = (string) $group->id;
        $this->groupProductId  = (string) $group->product_id;
        $this->groupName       = $group->name;
        $this->groupRequired   = $group->required;
        $this->groupMinChoices = $group->min_choices;
        $this->groupMaxChoices = $group->max_choices;
        $this->showGroupForm   = true;
    }

    public function saveGroup(): void
    {
        $this->validateOnly('groupName,groupProductId,groupRequired,groupMinChoices,groupMaxChoices', $this->groupRules());

        $data = [
            'product_id'  => $this->groupProductId,
            'name'        => $this->groupName,
            'required'    => $this->groupRequired,
            'min_choices' => $this->groupMinChoices,
            'max_choices' => $this->groupMaxChoices,
        ];

        if ($this->editingGroupId) {
            AddonGroup::findOrFail($this->editingGroupId)->update($data);
        } else {
            $data['sort_order'] = AddonGroup::max('sort_order') + 1;
            $data['is_active']  = true;
            AddonGroup::create($data);
        }

        $this->showGroupForm  = false;
        $this->editingGroupId = '';
        unset($this->groups);
    }

    public function deleteGroup(AddonGroup $group): void
    {
        $this->deleteError = null;
        $group->options()->delete();
        $group->delete();
        unset($this->groups);
    }

    public function cancelGroupForm(): void
    {
        $this->showGroupForm = false;
        $this->reset('editingGroupId', 'groupName', 'groupProductId');
    }

    // ── Option actions ─────────────────────────────────────────────────────

    public function openAddOption(int $groupId): void
    {
        $this->optionGroupId   = (string) $groupId;
        $this->editingOptionId = '';
        $this->optionName      = '';
        $this->optionPrice     = '0.00';
        $this->showOptionForm  = true;
    }

    public function openEditOption(AddonOption $option): void
    {
        $this->editingOptionId = (string) $option->id;
        $this->optionGroupId   = (string) $option->addon_group_id;
        $this->optionName      = $option->name;
        $this->optionPrice     = number_format((float) $option->price, 2, '.', '');
        $this->showOptionForm  = true;
    }

    public function saveOption(): void
    {
        $this->validateOnly('optionName,optionPrice', $this->optionRules());

        $data = [
            'name'  => $this->optionName,
            'price' => $this->optionPrice,
        ];

        if ($this->editingOptionId) {
            AddonOption::findOrFail($this->editingOptionId)->update($data);
        } else {
            $data['addon_group_id'] = $this->optionGroupId;
            $data['is_active']      = true;
            $data['sort_order']     = AddonOption::where('addon_group_id', $this->optionGroupId)->max('sort_order') + 1;
            AddonOption::create($data);
        }

        $this->showOptionForm  = false;
        $this->editingOptionId = '';
        unset($this->groups);
    }

    public function deleteOption(AddonOption $option): void
    {
        $option->delete();
        unset($this->groups);
    }

    public function cancelOptionForm(): void
    {
        $this->showOptionForm = false;
        $this->reset('editingOptionId', 'optionGroupId', 'optionName', 'optionPrice');
    }

    public function ruleLabel(AddonGroup $group): string
    {
        $prefix = $group->required ? 'Obrigatório' : 'Opcional';
        $max    = $group->max_choices > 1 ? "· até {$group->max_choices} opções" : '· 1 opção';
        return "{$prefix} {$max}";
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Complementos</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Grupos de complementos e suas opções por produto</p>
        </div>
        <button wire:click="openCreateGroup"
                class="text-sm text-white bg-orange-500 hover:bg-orange-600 rounded-lg px-3 py-1.5 transition">
            + Novo grupo
        </button>
    </div>

    @if($deleteError)
    <div class="mb-4 px-4 py-3 bg-red-500/10 border border-red-500/20 rounded-xl text-sm text-red-400">{{ $deleteError }}</div>
    @endif

    {{-- Group modal --}}
    @if($showGroupForm)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" wire:click.self="cancelGroupForm">
        <div class="bg-zinc-900 border border-zinc-700 rounded-2xl w-full max-w-sm mx-4 p-6 shadow-xl">
            <h2 class="text-base font-semibold text-white mb-5">
                {{ $editingGroupId ? 'Editar grupo' : 'Novo grupo de complementos' }}
            </h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">Produto *</label>
                    <select wire:model="groupProductId"
                            class="w-full bg-zinc-800 border border-zinc-700 text-zinc-300 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <option value="">Selecionar produto…</option>
                        @foreach($this->products as $product)
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
                    <span wire:loading.remove wire:target="saveGroup">{{ $editingGroupId ? 'Salvar' : 'Criar' }}</span>
                    <span wire:loading wire:target="saveGroup">Salvando…</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Option modal --}}
    @if($showOptionForm)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" wire:click.self="cancelOptionForm">
        <div class="bg-zinc-900 border border-zinc-700 rounded-2xl w-full max-w-xs mx-4 p-6 shadow-xl">
            <h2 class="text-base font-semibold text-white mb-5">
                {{ $editingOptionId ? 'Editar opção' : 'Nova opção' }}
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
                    <span wire:loading.remove wire:target="saveOption">{{ $editingOptionId ? 'Salvar' : 'Adicionar' }}</span>
                    <span wire:loading wire:target="saveOption">Salvando…</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Groups list --}}
    @if($this->groups->isEmpty())
    <div class="bg-zinc-900 border border-zinc-800 border-dashed rounded-xl px-5 py-14 text-center">
        <p class="text-white font-medium mb-1">Nenhum grupo de complementos cadastrado</p>
        <p class="text-zinc-500 text-sm mb-4">Crie grupos como "Ponto da carne" ou "Adicionais" e associe opções a eles.</p>
        <button wire:click="openCreateGroup" class="text-sm text-orange-400 hover:text-orange-300 transition">
            + Criar primeiro grupo
        </button>
    </div>
    @else
    <div class="space-y-4">
        @foreach($this->groups as $group)
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-zinc-800">
                <div>
                    <h2 class="text-sm font-semibold text-white">{{ $group->name }}</h2>
                    <p class="text-xs text-zinc-500 mt-0.5">
                        {{ $this->ruleLabel($group) }}
                        @if($group->product)
                        · <span class="text-zinc-600">{{ $group->product->name }}</span>
                        @endif
                    </p>
                </div>
                <div class="flex gap-2">
                    <button wire:click="openEditGroup({{ $group->id }})"
                            class="text-xs text-zinc-400 hover:text-white transition px-2 py-1 rounded-lg hover:bg-zinc-800">
                        Editar
                    </button>
                    <button wire:click="deleteGroup({{ $group->id }})"
                            wire:confirm="Excluir o grupo '{{ $group->name }}' e todas as suas opções?"
                            class="text-xs text-red-400/60 hover:text-red-400 transition px-2 py-1 rounded-lg hover:bg-zinc-800">
                        Excluir
                    </button>
                </div>
            </div>
            <div class="px-5 py-3 flex flex-wrap gap-2">
                @foreach($group->options as $option)
                <div class="group/opt inline-flex items-center gap-1.5 text-xs text-zinc-300 bg-zinc-800 border border-zinc-700 rounded-full pl-2.5 pr-1 py-1">
                    <span>{{ $option->name }}{{ $option->price > 0 ? ' (+R$ ' . number_format($option->price, 2, ',', '.') . ')' : '' }}</span>
                    <div class="flex gap-0.5 opacity-0 group-hover/opt:opacity-100 transition">
                        <button wire:click="openEditOption({{ $option->id }})"
                                class="text-zinc-500 hover:text-white rounded-full p-0.5 hover:bg-zinc-700 transition">
                            <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" /></svg>
                        </button>
                        <button wire:click="deleteOption({{ $option->id }})"
                                wire:confirm="Remover '{{ $option->name }}'?"
                                class="text-zinc-500 hover:text-red-400 rounded-full p-0.5 hover:bg-zinc-700 transition">
                            <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                </div>
                @endforeach
                <button wire:click="openAddOption({{ $group->id }})"
                        class="inline-flex items-center text-xs text-orange-400 bg-orange-400/10 border border-orange-400/20 rounded-full px-2.5 py-1 hover:bg-orange-400/20 transition">
                    + opção
                </button>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
