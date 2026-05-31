<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Enums\OrderStatus;
use App\Enums\DeliveryType;
use App\Models\Order;
use App\Models\Restaurant;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';
    #[Url]
    public string $status = '';
    #[Url]
    public string $date   = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatus(): void { $this->resetPage(); }
    public function updatedDate():   void { $this->resetPage(); }

    #[Computed]
    public function orders()
    {
        return Order::query()
            ->where('restaurant_id', Restaurant::query()->value('id'))
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('number', 'like', "%{$this->search}%")
                  ->orWhere('customer_name', 'like', "%{$this->search}%");
            }))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->date,   fn ($q) => $q->whereDate('created_at', $this->date))
            ->latest()
            ->paginate(20);
    }

    #[Computed]
    public function statusOptions(): array
    {
        return OrderStatus::cases();
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Pedidos</h1>
            <p class="text-sm text-zinc-400 mt-0.5">
                {{ $this->orders->total() }} pedido(s) encontrado(s)
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.orders.in-progress') }}" wire:navigate
               class="text-sm text-orange-400 bg-orange-400/10 hover:bg-orange-400/15 border border-orange-400/20 rounded-lg px-3 py-1.5 transition">
                Em andamento
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="flex flex-col sm:flex-row gap-3 mb-5">
        <div class="relative flex-1">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Buscar por número ou cliente..."
                class="w-full pl-9 pr-4 py-2.5 bg-zinc-900 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl focus:outline-none focus:ring-2 focus:ring-orange-500"
            />
        </div>
        <select wire:model.live="status"
                class="bg-zinc-900 border border-zinc-700 text-zinc-300 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500">
            <option value="">Todos os status</option>
            @foreach($this->statusOptions as $s)
            <option value="{{ $s->value }}">{{ $s->label() }}</option>
            @endforeach
        </select>
        <input
            type="date"
            wire:model.live="date"
            class="bg-zinc-900 border border-zinc-700 text-zinc-300 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500"
        />
    </div>

    @php
    $statusColors = [
        'yellow' => 'bg-yellow-400/10 text-yellow-400',
        'blue'   => 'bg-blue-400/10 text-blue-400',
        'orange' => 'bg-orange-400/10 text-orange-400',
        'purple' => 'bg-purple-400/10 text-purple-400',
        'indigo' => 'bg-indigo-400/10 text-indigo-400',
        'teal'   => 'bg-teal-400/10 text-teal-400',
        'green'  => 'bg-green-400/10 text-green-400',
        'red'    => 'bg-red-400/10 text-red-400',
        'gray'   => 'bg-zinc-700 text-zinc-400',
    ];
    @endphp

    <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
        @if($this->orders->isEmpty())
        <div class="px-5 py-16 text-center">
            @if($search || $status || $date)
                <p class="text-zinc-400 text-sm font-medium">Nenhum pedido encontrado para os filtros selecionados.</p>
                <p class="text-zinc-600 text-xs mt-1">Tente ajustar os filtros acima.</p>
            @else
                <p class="text-zinc-400 text-sm font-medium">Nenhum pedido registrado ainda.</p>
                <p class="text-zinc-600 text-xs mt-1">Os pedidos dos clientes aparecerão aqui.</p>
            @endif
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800">
                        <th class="text-left px-5 py-3 text-xs font-medium text-zinc-500">Pedido</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-zinc-500">Cliente</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-zinc-500">Origem</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-zinc-500">Status</th>
                        <th class="text-right px-4 py-3 text-xs font-medium text-zinc-500">Total</th>
                        <th class="text-right px-5 py-3 text-xs font-medium text-zinc-500">Data</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/70">
                    @foreach($this->orders as $order)
                    <tr
                        wire:navigate
                        href="{{ route('admin.orders.show', $order) }}"
                        class="hover:bg-zinc-800/40 transition cursor-pointer"
                        onclick="window.location='{{ route('admin.orders.show', $order) }}'"
                    >
                        <td class="px-5 py-3.5">
                            <span class="text-xs font-mono text-zinc-400">#{{ $order->number }}</span>
                        </td>
                        <td class="px-4 py-3.5 text-zinc-300 font-medium">
                            {{ $order->customer_name ?: '—' }}
                        </td>
                        <td class="px-4 py-3.5 text-zinc-400 text-xs">
                            {{ $order->delivery_type === DeliveryType::Delivery ? 'Delivery' : 'Balcão' }}
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $statusColors[$order->status->color()] ?? 'bg-zinc-700 text-zinc-400' }}">
                                {{ $order->status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-right text-zinc-200 font-medium tabular-nums">
                            R$ {{ number_format($order->total, 2, ',', '.') }}
                        </td>
                        <td class="px-5 py-3.5 text-right text-xs text-zinc-500 tabular-nums">
                            {{ $order->created_at->format('d/m/y H:i') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($this->orders->hasPages())
        <div class="px-5 py-4 border-t border-zinc-800">
            {{ $this->orders->links() }}
        </div>
        @endif
        @endif
    </div>
</div>
