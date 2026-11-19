<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\OrderItem;
use App\Support\CurrentRestaurant;
use Illuminate\Support\Carbon;

/*
 * Restaurant view of one customer (EF-23): contact, addresses, numbers and the
 * orders placed at this restaurant. Same fence as the customers list.
 */
new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public Customer $customer;

    /** Not paid: left out of the total, the average ticket and the top items. */
    private const UNPAID = [OrderStatus::Canceled, OrderStatus::Draft];

    public function mount(Customer $customer): void
    {
        // Another restaurant's customer: 404, like an id that does not exist.
        abort_unless(
            Customer::ofRestaurant(CurrentRestaurant::id())->whereKey($customer->id)->exists(),
            404,
        );

        $this->customer = $customer->load('addresses');
    }

    /** This customer's orders at this restaurant. */
    private function ordersQuery()
    {
        return $this->customer->orders()->where('restaurant_id', CurrentRestaurant::id());
    }

    private function unpaidValues(): array
    {
        return array_map(fn (OrderStatus $status) => $status->value, self::UNPAID);
    }

    #[Computed]
    public function stats(): array
    {
        $paid  = $this->ordersQuery()->whereNotIn('status', $this->unpaidValues());
        $spent = (float) (clone $paid)->sum('total');
        $paidCount = (clone $paid)->count();
        $last  = $this->ordersQuery()->max('created_at');

        return [
            'order_count'    => $this->ordersQuery()->count(),
            'total_spent'    => $spent,
            'average_ticket' => $paidCount > 0 ? round($spent / $paidCount, 2) : null,
            'last_order_at'  => $last ? Carbon::parse($last) : null,
        ];
    }

    /** Most ordered items (by quantity, from the item name snapshot), paid orders only. */
    #[Computed]
    public function topItems(): array
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.customer_id', $this->customer->id)
            ->where('orders.restaurant_id', CurrentRestaurant::id())
            ->whereNotIn('orders.status', $this->unpaidValues())
            ->groupBy('order_items.product_name')
            ->selectRaw('order_items.product_name, SUM(order_items.quantity) as quantity')
            ->orderByDesc('quantity')
            ->orderBy('order_items.product_name')
            ->limit(5)
            ->get()
            ->map(fn ($row) => ['product_name' => $row->product_name, 'quantity' => (int) $row->quantity])
            ->all();
    }

    #[Computed]
    public function orders()
    {
        return $this->ordersQuery()->latest()->orderByDesc('id')->paginate(10);
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="{{ route('admin.customers.index') }}" wire:navigate
               class="text-xs text-zinc-500 hover:text-zinc-300 transition">← Clientes</a>
            <h1 class="text-xl font-bold text-white mt-1">{{ $customer->name }}</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Cliente desde {{ $customer->created_at->format('d/m/Y') }}</p>
        </div>
    </div>

    @php
    $stats = $this->stats;
    $money = fn ($value) => 'R$ ' . number_format((float) $value, 2, ',', '.');
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

    {{-- Numbers --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3 text-center">
            <p class="text-2xl font-bold text-white tabular-nums">{{ $stats['order_count'] }}</p>
            <p class="text-xs text-zinc-400 mt-1">Pedidos</p>
        </div>
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3 text-center">
            <p class="text-xl font-bold text-white tabular-nums">{{ $money($stats['total_spent']) }}</p>
            <p class="text-xs text-zinc-400 mt-1">Total gasto</p>
        </div>
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3 text-center">
            <p class="text-xl font-bold text-white tabular-nums">
                {{ $stats['average_ticket'] !== null ? $money($stats['average_ticket']) : '—' }}
            </p>
            <p class="text-xs text-zinc-400 mt-1">Ticket médio</p>
        </div>
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3 text-center">
            <p class="text-base font-bold text-white tabular-nums">
                {{ $stats['last_order_at']?->format('d/m/Y H:i') ?? '—' }}
            </p>
            <p class="text-xs text-zinc-400 mt-1">Último pedido</p>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3 mb-6">
        {{-- Contact --}}
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-5 py-4">
            <h2 class="text-sm font-semibold text-white mb-3">Contato</h2>
            <dl class="space-y-2 text-sm">
                <div>
                    <dt class="text-xs text-zinc-500">E-mail</dt>
                    <dd class="text-zinc-300 break-all">{{ $customer->email ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-zinc-500">Telefone</dt>
                    <dd class="text-zinc-300 tabular-nums">{{ $customer->phone ?: '—' }}</dd>
                </div>
            </dl>
        </div>

        {{-- Addresses --}}
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-5 py-4">
            <h2 class="text-sm font-semibold text-white mb-3">Endereços</h2>
            @forelse($customer->addresses as $address)
            <div class="text-sm {{ ! $loop->last ? 'mb-3' : '' }}">
                <p class="text-xs text-zinc-500">
                    {{ $address->label ?: 'Endereço' }}
                    @if($address->is_default)
                    <span class="ml-1 inline-flex items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium bg-orange-400/10 text-orange-400">Padrão</span>
                    @endif
                </p>
                <p class="text-zinc-300">{{ $address->formatted() }}</p>
            </div>
            @empty
            <p class="text-sm text-zinc-500">Nenhum endereço cadastrado.</p>
            @endforelse
        </div>

        {{-- Top items --}}
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-5 py-4">
            <h2 class="text-sm font-semibold text-white mb-3">Mais pedidos</h2>
            @forelse($this->topItems as $item)
            <div class="flex items-center justify-between text-sm {{ ! $loop->last ? 'mb-2' : '' }}">
                <span class="text-zinc-300">{{ $item['product_name'] }}</span>
                <span class="text-zinc-500 tabular-nums">{{ $item['quantity'] }}×</span>
            </div>
            @empty
            <p class="text-sm text-zinc-500">Sem itens ainda.</p>
            @endforelse
        </div>
    </div>

    {{-- Orders --}}
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
        <div class="px-5 py-3 border-b border-zinc-800">
            <h2 class="text-sm font-semibold text-white">Pedidos</h2>
        </div>
        @if($this->orders->isEmpty())
        <div class="px-5 py-12 text-center">
            <p class="text-zinc-500 text-sm">Nenhum pedido deste cliente ainda.</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-zinc-800">
                        <th class="text-left px-5 py-3 text-xs font-medium text-zinc-500">Pedido</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-zinc-500">Origem</th>
                        <th class="text-left px-4 py-3 text-xs font-medium text-zinc-500">Status</th>
                        <th class="text-right px-4 py-3 text-xs font-medium text-zinc-500">Total</th>
                        <th class="text-right px-5 py-3 text-xs font-medium text-zinc-500">Data</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/70">
                    @foreach($this->orders as $order)
                    <tr class="hover:bg-zinc-800/40 transition">
                        <td class="px-5 py-3.5">
                            <a href="{{ route('admin.orders.show', $order) }}" wire:navigate
                               class="text-xs font-mono text-zinc-400 hover:text-orange-400 transition">#{{ $order->number }}</a>
                        </td>
                        <td class="px-4 py-3.5 text-zinc-400 text-xs">
                            <x-orders.delivery-type :order="$order" />
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $statusColors[$order->status->color()] ?? 'bg-zinc-700 text-zinc-400' }}">
                                {{ $order->status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-right text-zinc-200 font-medium tabular-nums">
                            {{ $money($order->total) }}
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
