<?php
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    // Fase MVP: gerenciamento de grupos de complementos e opções de add-on
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

    <div class="space-y-4">
        @foreach([
            [
                'group' => 'Ponto da carne',
                'rule'  => 'Obrigatório · 1 opção',
                'items' => ['Mal passado', 'Ao ponto', 'Bem passado'],
            ],
            [
                'group' => 'Adicionais',
                'rule'  => 'Opcional · até 5 opções',
                'items' => ['Queijo extra (+R$ 3,00)', 'Bacon (+R$ 4,00)', 'Ovo (+R$ 2,50)', 'Cebola caramelizada (+R$ 3,50)'],
            ],
            [
                'group' => 'Tamanho da batata',
                'rule'  => 'Obrigatório · 1 opção',
                'items' => ['Pequena', 'Média (+R$ 5,00)', 'Grande (+R$ 8,00)'],
            ],
        ] as $group)
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-zinc-800">
                <div>
                    <h2 class="text-sm font-semibold text-white">{{ $group['group'] }}</h2>
                    <p class="text-xs text-zinc-500 mt-0.5">{{ $group['rule'] }}</p>
                </div>
                <div class="flex gap-2">
                    <button class="text-xs text-zinc-400 hover:text-white transition px-2 py-1 rounded-lg hover:bg-zinc-800">Editar</button>
                    <button class="text-xs text-zinc-400 hover:text-red-400 transition px-2 py-1 rounded-lg hover:bg-zinc-800">Remover</button>
                </div>
            </div>
            <div class="px-5 py-3 flex flex-wrap gap-2">
                @foreach($group['items'] as $item)
                <span class="inline-flex items-center text-xs text-zinc-300 bg-zinc-800 border border-zinc-700 rounded-full px-2.5 py-1">
                    {{ $item }}
                </span>
                @endforeach
                <button class="inline-flex items-center text-xs text-orange-400 bg-orange-400/10 border border-orange-400/20 rounded-full px-2.5 py-1 hover:bg-orange-400/20 transition">
                    + opção
                </button>
            </div>
        </div>
        @endforeach
    </div>

    <div class="mt-4 px-4 py-3 bg-zinc-900 border border-zinc-800 rounded-xl">
        <p class="text-xs text-zinc-600 italic text-center">
            Gerencia os modelos <code class="text-zinc-500">AddonGroup</code> e <code class="text-zinc-500">AddonOption</code> — CRUD a implementar.
        </p>
    </div>
</div>
