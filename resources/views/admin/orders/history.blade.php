<x-layouts.app>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Histórico de pedidos</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Pedidos concluídos e cancelados</p>
        </div>
        <a href="{{ route('admin.orders.index') }}" wire:navigate
           class="text-sm text-zinc-300 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 rounded-lg px-3 py-1.5 transition">
            Voltar
        </a>
    </div>

    {{-- Filters --}}
    <div class="flex gap-3 mb-5">
        <div class="relative">
            <input type="date" class="bg-zinc-900 border border-zinc-700 text-zinc-300 text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-500">
        </div>
        <select class="bg-zinc-900 border border-zinc-700 text-zinc-400 text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-500">
            <option>Todos os status finais</option>
            <option>Concluídos</option>
            <option>Cancelados</option>
        </select>
    </div>

    <div class="bg-zinc-900 border border-zinc-800 rounded-xl">
        <div class="px-5 py-3 border-b border-zinc-800">
            <p class="text-xs text-zinc-600 italic">
                Implementar com <code class="text-zinc-500">Order::whereIn('status', ['completed', 'canceled'])</code>
                + filtros de data e paginação.
            </p>
        </div>
        <div class="px-5 py-12 text-center">
            <p class="text-zinc-500 text-sm">Nenhum pedido no histórico ainda.</p>
        </div>
    </div>
</x-layouts.app>
