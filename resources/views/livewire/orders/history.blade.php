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
    public string $status = '';
    #[Url]
    public string $date   = '';

    public function updatedStatus(): void { $this->resetPage(); }
    public function updatedDate():   void { $this->resetPage(); }

    #[Computed]
    public function orders()
    {
        $finalStatuses = [
            OrderStatus::Delivered->value,
            OrderStatus::Completed->value,
            OrderStatus::Canceled->value,
        ];

        return Order::whereIn('status', $finalStatuses)
            ->where('restaurant_id', Restaurant::query()->value('id'))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->date,   fn ($q) => $q->whereDate('created_at', $this->date))
            ->latest()
            ->paginate(25);
    }

    #[Computed]
    public function totals(): array
    {
        $finalStatuses = [
            OrderStatus::Delivered->value,
            OrderStatus::Completed->value,
            OrderStatus::Canceled->value,
        ];

        $all = Order::whereIn('status', $finalStatuses)
            ->when($this->date, fn ($q) => $q->whereDate('created_at', $this->date))
            ->get(['status', 'total']);

        return [
            'total'     => $all->count(),
            'completed' => $all->whereIn('status', [OrderStatus::Delivered->value, OrderStatus::Completed->value])->count(),
            'canceled'  => $all->where('status', OrderStatus::Canceled->value)->count(),
            'revenue'   => $all->whereIn('status', [OrderStatus::Delivered->value, OrderStatus::Completed->value])->sum('total'),
        ];
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Histórico de pedidos</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Pedidos entregues, concluídos e cancelados</p>
        </div>
        <a href="{{ route('admin.orders.index') }}" wire:navigate
           class="text-sm text-zinc-300 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 rounded-lg px-3 py-1.5 transition">
            Todos os pedidos
        </a>
    </div>

    {{-- Summary --}}
    @php $t = $this->totals; @endphp
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3 text-center">
            <p class="text-2xl font-bold text-white tabular-nums">{{ $t['total'] }}</p>
            <p class="text-xs text-zinc-400 mt-1">Total no período</p>
        </div>
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3 text-center">
            <p class="text-2xl font-bold text-green-400 tabular-nums">{{ $t['completed'] }}</p>
            <p class="text-xs text-zinc-400 mt-1">Concluídos/Entregues</p>
        </div>
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3 text-center">
            <p class="text-2xl font-bold text-red-400 tabular-nums">{{ $t['canceled'] }}</p>
            <p class="text-xs text-zinc-400 mt-1">Cancelados</p>
        </div>
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3 text-center">
            <p class="text-xl font-bold text-white tabular-nums">R$ {{ number_format($t['revenue'], 2, ',', '.') }}</p>
            <p class="text-xs text-zinc-400 mt-1">Faturamento</p>
        </div>
    </div>

    {{-- Filters --}}
    <div class="flex gap-3 mb-5">
        <input
            type="date"
            wire:model.live="date"
            class="bg-zinc-900 border border-zinc-700 text-zinc-300 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500"
        />
        <select wire:model.live="status"
                class="bg-zinc-900 border border-zinc-700 text-zinc-300 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500">
            <option value="">Todos os status</option>
            <option value="{{ OrderStatus::Delivered->value }}">{{ OrderStatus::Delivered->label() }}</option>
            <option value="{{ OrderStatus::Completed->value }}">{{ OrderStatus::Completed->label() }}</option>
            <option value="{{ OrderStatus::Canceled->value }}">{{ OrderStatus::Canceled->label() }}</option>
        </select>
    </div>

    @php
    $statusColors = [
        'teal'  => 'bg-teal-400/10 text-teal-400',
        'green' => 'bg-green-400/10 text-green-400',
        'red'   => 'bg-red-400/10 text-red-400',
    ];
    @endphp

    <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
        @if($this->orders->isEmpty())
        <div class="px-5 py-16 text-center">
            <p class="text-zinc-500 text-sm">Nenhum pedido no histórico para os filtros selecionados.</p>
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
