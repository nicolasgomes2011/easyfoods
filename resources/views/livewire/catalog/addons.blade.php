<?php
use App\Models\AddonGroup;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    #[Computed]
    public function groups()
    {
        return AddonGroup::with(['product', 'options'])->orderBy('sort_order')->get();
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
        <button class="text-sm text-white bg-orange-500 hover:bg-orange-600 rounded-lg px-3 py-1.5 transition">
            + Novo grupo
        </button>
    </div>

    @if($this->groups->isEmpty())
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-5 py-14 text-center">
        <div class="size-10 rounded-xl bg-zinc-800 flex items-center justify-center mx-auto mb-3">
            <svg class="size-5 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
        </div>
        <p class="text-white font-medium mb-1">Nenhum grupo de complementos cadastrado</p>
        <p class="text-zinc-500 text-sm">Crie grupos como "Ponto da carne" ou "Adicionais" e associe opções a eles.</p>
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
                    <button class="text-xs text-zinc-400 hover:text-white transition px-2 py-1 rounded-lg hover:bg-zinc-800">Editar</button>
                    <button class="text-xs text-zinc-400 hover:text-red-400 transition px-2 py-1 rounded-lg hover:bg-zinc-800">Remover</button>
                </div>
            </div>
            <div class="px-5 py-3 flex flex-wrap gap-2">
                @foreach($group->options as $option)
                <span class="inline-flex items-center text-xs text-zinc-300 bg-zinc-800 border border-zinc-700 rounded-full px-2.5 py-1">
                    {{ $option->name }}{{ $option->price > 0 ? ' (+R$ ' . number_format($option->price, 2, ',', '.') . ')' : '' }}
                </span>
                @endforeach
                <button class="inline-flex items-center text-xs text-orange-400 bg-orange-400/10 border border-orange-400/20 rounded-full px-2.5 py-1 hover:bg-orange-400/20 transition">
                    + opção
                </button>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
