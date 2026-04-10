<?php
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public string $search = '';
    // Fase MVP: listagem de clientes com histórico de pedidos
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Clientes</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Cadastro e histórico de pedidos</p>
        </div>
    </div>

    {{-- Search --}}
    <div class="mb-5">
        <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Buscar por nome, email ou telefone..."
                class="w-full pl-9 pr-4 py-2.5 bg-zinc-900 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
            >
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800">
                        <th class="text-left text-xs font-medium text-zinc-400 px-5 py-3">Cliente</th>
                        <th class="text-left text-xs font-medium text-zinc-400 px-4 py-3">Telefone</th>
                        <th class="text-left text-xs font-medium text-zinc-400 px-4 py-3">Pedidos</th>
                        <th class="text-left text-xs font-medium text-zinc-400 px-4 py-3">Total gasto</th>
                        <th class="text-left text-xs font-medium text-zinc-400 px-4 py-3">Último pedido</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/70">
                    @foreach([
                        ['name' => 'Ana Paula S.',   'email' => 'ana@email.com',    'phone' => '(11) 99001-2345', 'orders' => 14, 'total' => 'R$ 892,50',  'last' => 'há 2 dias'],
                        ['name' => 'Carlos M.',      'email' => 'carlos@email.com', 'phone' => '(11) 98765-4321', 'orders' => 8,  'total' => 'R$ 510,00',  'last' => 'há 5 dias'],
                        ['name' => 'Beatriz L.',     'email' => 'bea@email.com',    'phone' => '(11) 97654-3210', 'orders' => 22, 'total' => 'R$ 1.430,80','last' => 'hoje'],
                        ['name' => 'Rafael T.',      'email' => 'rafa@email.com',   'phone' => '(11) 96543-2109', 'orders' => 3,  'total' => 'R$ 187,00',  'last' => 'há 3 sem.'],
                        ['name' => 'Mariana C.',     'email' => 'mari@email.com',   'phone' => '(11) 95432-1098', 'orders' => 11, 'total' => 'R$ 734,20',  'last' => 'há 1 dia'],
                    ] as $customer)
                    <tr class="hover:bg-zinc-800/40 transition">
                        <td class="px-5 py-3.5">
                            <div>
                                <p class="font-medium text-white">{{ $customer['name'] }}</p>
                                <p class="text-xs text-zinc-500">{{ $customer['email'] }}</p>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-zinc-400 text-xs tabular-nums">{{ $customer['phone'] }}</td>
                        <td class="px-4 py-3.5 text-zinc-300 font-medium tabular-nums">{{ $customer['orders'] }}</td>
                        <td class="px-4 py-3.5 text-zinc-300 tabular-nums">{{ $customer['total'] }}</td>
                        <td class="px-4 py-3.5 text-zinc-500 text-xs">{{ $customer['last'] }}</td>
                        <td class="px-4 py-3.5">
                            <button class="text-xs text-orange-400 hover:text-orange-300 transition">Ver histórico</button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3 border-t border-zinc-800">
            <p class="text-xs text-zinc-600 italic">Dados de exemplo. Conectar ao modelo <code class="text-zinc-500">Customer</code> com buscas por nome/email/telefone.</p>
        </div>
    </div>
</div>
