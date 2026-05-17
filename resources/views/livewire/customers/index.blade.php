<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Enums\OrderStatus;
use App\Models\Customer;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public function updatedSearch(): void { $this->resetPage(); }

    #[Computed]
    public function customers()
    {
        return Customer::withCount('orders as order_count')
            ->withSum(['orders as total_spent' => fn ($q) => $q->whereNotIn('status', [
                OrderStatus::Canceled->value,
                OrderStatus::Draft->value,
            ])], 'total')
            ->withMax('orders as last_order_at', 'created_at')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('name',  'like', "%{$this->search}%")
                  ->orWhere('email', 'like', "%{$this->search}%")
                  ->orWhere('phone', 'like', "%{$this->search}%");
            }))
            ->orderByDesc('last_order_at')
            ->paginate(25);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Clientes</h1>
            <p class="text-sm text-zinc-400 mt-0.5">
                {{ $this->customers->total() }} cliente(s) cadastrado(s)
            </p>
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

    <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
        @if($this->customers->isEmpty())
        <div class="px-5 py-16 text-center">
            <p class="text-zinc-500 text-sm">
                @if($this->search)
                Nenhum cliente encontrado para "{{ $this->search }}".
                @else
                Nenhum cliente cadastrado ainda.
                @endif
            </p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800">
                        <th class="text-left text-xs font-medium text-zinc-500 px-5 py-3">Cliente</th>
                        <th class="text-left text-xs font-medium text-zinc-500 px-4 py-3">Telefone</th>
                        <th class="text-right text-xs font-medium text-zinc-500 px-4 py-3">Pedidos</th>
                        <th class="text-right text-xs font-medium text-zinc-500 px-4 py-3">Total gasto</th>
                        <th class="text-right text-xs font-medium text-zinc-500 px-5 py-3">Último pedido</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/70">
                    @foreach($this->customers as $customer)
                    <tr class="hover:bg-zinc-800/40 transition">
                        <td class="px-5 py-3.5">
                            <div>
                                <p class="font-medium text-white">{{ $customer->name }}</p>
                                <p class="text-xs text-zinc-500">{{ $customer->email }}</p>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-zinc-400 text-xs tabular-nums">
                            {{ $customer->phone ?: '—' }}
                        </td>
                        <td class="px-4 py-3.5 text-right text-zinc-300 font-medium tabular-nums">
                            {{ $customer->order_count }}
                        </td>
                        <td class="px-4 py-3.5 text-right text-zinc-300 tabular-nums">
                            R$ {{ number_format($customer->total_spent ?? 0, 2, ',', '.') }}
                        </td>
                        <td class="px-5 py-3.5 text-right text-zinc-500 text-xs tabular-nums">
                            {{ $customer->last_order_at
                                ? \Carbon\Carbon::parse($customer->last_order_at)->diffForHumans()
                                : '—' }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($this->customers->hasPages())
        <div class="px-5 py-4 border-t border-zinc-800">
            {{ $this->customers->links() }}
        </div>
        @endif
        @endif
    </div>
</div>
