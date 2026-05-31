<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Produtos</h1>
            <p class="text-sm text-zinc-400 mt-0.5">{{ $this->products->count() }} produto(s) no cardápio</p>
        </div>
        <a href="{{ route('admin.catalog.products.create') }}" wire:navigate
           class="text-sm text-white bg-orange-500 hover:bg-orange-600 rounded-lg px-3 py-1.5 transition">
            + Novo produto
        </a>
    </div>

    <div class="flex gap-3 mb-4">
        <input
            wire:model.live.debounce.300ms="search"
            type="text"
            placeholder="Buscar produto..."
            class="flex-1 bg-zinc-900 border border-zinc-800 rounded-lg px-3 py-2 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-orange-500"
        />

        <select wire:model.live="categoryFilter" class="bg-zinc-900 border border-zinc-800 rounded-lg px-3 py-2 text-sm text-zinc-300 focus:outline-none focus:border-orange-500">
            <option value="">Todas as categorias</option>
            @foreach($this->categories as $cat)
                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="statusFilter" class="bg-zinc-900 border border-zinc-800 rounded-lg px-3 py-2 text-sm text-zinc-300 focus:outline-none focus:border-orange-500">
            <option value="">Todos os status</option>
            @foreach($this->statuses() as $status)
                <option value="{{ $status->value }}">{{ $status->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
        @if($this->products->isEmpty())
            <div class="px-5 py-16 text-center">
                <p class="text-zinc-400 text-sm font-medium">Nenhum produto encontrado.</p>
                <p class="text-zinc-600 text-xs mt-1">O cardápio está vazio. Adicione o primeiro produto.</p>
                <a href="{{ route('admin.catalog.products.create') }}" wire:navigate
                   class="inline-block mt-4 text-sm text-white bg-orange-500 hover:bg-orange-600 rounded-lg px-4 py-2 transition">
                    + Novo produto
                </a>
            </div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800">
                        <th class="text-left px-5 py-3 text-xs font-medium text-zinc-500 uppercase tracking-wider">Produto</th>
                        <th class="text-left px-5 py-3 text-xs font-medium text-zinc-500 uppercase tracking-wider">Categoria</th>
                        <th class="text-left px-5 py-3 text-xs font-medium text-zinc-500 uppercase tracking-wider">Preço</th>
                        <th class="text-left px-5 py-3 text-xs font-medium text-zinc-500 uppercase tracking-wider">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @foreach($this->products as $product)
                    <tr class="hover:bg-zinc-800/50 transition">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-white">{{ $product->name }}</span>
                                @if($product->is_featured)
                                    <span class="text-[10px] font-semibold text-orange-400 bg-orange-400/10 border border-orange-400/20 rounded-full px-1.5 py-0.5">destaque</span>
                                @endif
                            </div>
                            @if($product->description)
                                <p class="text-xs text-zinc-500 mt-0.5 truncate max-w-xs">{{ $product->description }}</p>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-zinc-400">{{ $product->category?->name ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-zinc-300">R$ {{ number_format($product->price, 2, ',', '.') }}</td>
                        <td class="px-5 py-3.5">
                            @php $status = $product->availability_status @endphp
                            @if($status === \App\Enums\ProductAvailabilityStatus::Available)
                                <span class="inline-flex items-center text-xs font-medium rounded-full px-2 py-0.5 text-green-400 bg-green-400/10">
                                    {{ $status->label() }}
                                </span>
                            @elseif($status === \App\Enums\ProductAvailabilityStatus::OutOfStock)
                                <span class="inline-flex items-center text-xs font-medium rounded-full px-2 py-0.5 text-red-400 bg-red-400/10">
                                    {{ $status->label() }}
                                </span>
                            @else
                                <span class="inline-flex items-center text-xs font-medium rounded-full px-2 py-0.5 text-zinc-400 bg-zinc-800">
                                    {{ $status->label() }}
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('admin.catalog.products.edit', $product) }}"
                                   class="text-xs text-zinc-400 hover:text-white transition px-2 py-1 rounded-lg hover:bg-zinc-800">
                                    Editar
                                </a>
                                <button
                                    wire:click="delete({{ $product->id }})"
                                    wire:confirm="Remover '{{ $product->name }}'? Esta ação não pode ser desfeita."
                                    class="text-zinc-600 hover:text-red-400 transition p-1 rounded-lg hover:bg-zinc-800"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
