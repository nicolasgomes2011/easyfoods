<?php
use App\Models\AddonGroup;
use App\Models\AddonOption;
use App\Models\Product;
use App\Support\CurrentRestaurant;
use Illuminate\Validation\Rule;
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
            // Product must belong to this restaurant — a raw exists: would accept
            // another restaurant's product id (cross-tenant write).
            'groupProductId'  => ['required', Rule::exists('products', 'id')->where('restaurant_id', $this->rid())],
            'groupRequired'   => 'boolean',
            'groupMinChoices' => 'required|integer|min:0',
            'groupMaxChoices' => 'required|integer|min:1|gte:groupMinChoices',
        ];
    }

    public function optionRules(): array
    {
        return [
            'optionName'  => 'required|string|max:100',
            'optionPrice' => 'required|numeric|min:0',
        ];
    }

    /**
     * Single-tenant shortcut — same convention as every other panel component.
     * CurrentRestaurant resolves with an explicit ORDER BY id, so every
     * component agrees on the same restaurant once a 2nd one exists.
     */
    private function rid(): ?int
    {
        return CurrentRestaurant::id();
    }

    #[Computed]
    public function products()
    {
        if (! $this->rid()) return collect();
        return Product::where('restaurant_id', $this->rid())
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function groups()
    {
        if (! $this->rid()) return collect();
        return AddonGroup::whereHas('product', fn ($q) => $q->where('restaurant_id', $this->rid()))
            ->with(['product:id,name', 'options'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /** Group lookup fenced to this restaurant (via its product) — bare findOrFail would cross tenants. */
    private function findGroup(int $groupId): AddonGroup
    {
        return AddonGroup::whereHas('product', fn ($q) => $q->where('restaurant_id', $this->rid()))
            ->findOrFail($groupId);
    }

    /** Option lookup fenced to this restaurant (via its group's product). */
    private function findOption(int $optionId): AddonOption
    {
        return AddonOption::whereHas('group.product', fn ($q) => $q->where('restaurant_id', $this->rid()))
            ->findOrFail($optionId);
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

    public function openEditGroup(int $groupId): void
    {
        $group = $this->findGroup($groupId);

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
        // validate() (not validateOnly with a comma list — that matches no rule key
        // and would silently validate nothing).
        $this->validate($this->groupRules());

        $data = [
            'product_id'  => $this->groupProductId,
            'name'        => $this->groupName,
            'required'    => $this->groupRequired,
            'min_choices' => $this->groupMinChoices,
            'max_choices' => $this->groupMaxChoices,
        ];

        if ($this->editingGroupId) {
            $this->findGroup((int) $this->editingGroupId)->update($data);
        } else {
            $data['sort_order'] = (int) AddonGroup::whereHas('product', fn ($q) => $q->where('restaurant_id', $this->rid()))->max('sort_order') + 1;
            $data['is_active']  = true;
            AddonGroup::create($data);
        }

        $this->showGroupForm  = false;
        $this->editingGroupId = '';
        unset($this->groups);
    }

    public function deleteGroup(int $groupId): void
    {
        $group = $this->findGroup($groupId);

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

    public function openEditOption(int $optionId): void
    {
        $option = $this->findOption($optionId);

        $this->editingOptionId = (string) $option->id;
        $this->optionGroupId   = (string) $option->addon_group_id;
        $this->optionName      = $option->name;
        $this->optionPrice     = number_format((float) $option->price, 2, '.', '');
        $this->showOptionForm  = true;
    }

    public function saveOption(): void
    {
        $this->validate($this->optionRules());

        $data = [
            'name'  => $this->optionName,
            'price' => $this->optionPrice,
        ];

        if ($this->editingOptionId) {
            $this->findOption((int) $this->editingOptionId)->update($data);
        } else {
            // Fence the target group to this restaurant before attaching the option.
            $group = $this->findGroup((int) $this->optionGroupId);

            $data['addon_group_id'] = $group->id;
            $data['is_active']      = true;
            $data['sort_order']     = (int) AddonOption::where('addon_group_id', $group->id)->max('sort_order') + 1;
            AddonOption::create($data);
        }

        $this->showOptionForm  = false;
        $this->editingOptionId = '';
        unset($this->groups);
    }

    public function deleteOption(int $optionId): void
    {
        $this->findOption($optionId)->delete();
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
    <x-catalog.addon-group-form :products="$this->products" :editing="(bool) $editingGroupId" />
    @endif

    {{-- Option modal --}}
    @if($showOptionForm)
    <x-catalog.addon-option-form :editing="(bool) $editingOptionId" />
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
