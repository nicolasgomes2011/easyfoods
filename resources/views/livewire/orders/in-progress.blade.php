<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Poll;
use Livewire\Volt\Component;
use App\Enums\OrderStatus;
use App\Enums\DeliveryType;
use App\Models\Order;

new #[Layout('components.layouts.app')] #[Poll(30000)] class extends Component {

    #[Computed]
    public function buckets(): array
    {
        return [
            ['status' => OrderStatus::PendingConfirmation, 'label' => 'Aguardando',  'color' => 'yellow'],
            ['status' => OrderStatus::Confirmed,           'label' => 'Confirmados', 'color' => 'blue'],
            ['status' => OrderStatus::InPreparation,       'label' => 'Em preparo',  'color' => 'orange'],
            ['status' => OrderStatus::ReadyForPickup,      'label' => 'Prontos',     'color' => 'green'],
            ['status' => OrderStatus::OutForDelivery,      'label' => 'Em entrega',  'color' => 'indigo'],
        ];
    }

    #[Computed]
    public function orders()
    {
        $activeStatuses = array_map(
            fn ($b) => $b['status']->value,
            $this->buckets
        );

        return Order::whereIn('status', $activeStatuses)
            ->orderBy('created_at')
            ->get();
    }

    #[Computed]
    public function countByStatus(): array
    {
        return $this->orders
            ->groupBy(fn ($o) => $o->status->value)
            ->map->count()
            ->toArray();
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Pedidos em andamento</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Pedidos ativos neste momento · atualiza a cada 30s</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.orders.index') }}" wire:navigate
               class="text-sm text-zinc-300 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 rounded-lg px-3 py-1.5 transition">
                Todos os pedidos
            </a>
            <a href="{{ route('admin.kitchen.index') }}" wire:navigate
               class="text-sm text-white bg-orange-500 hover:bg-orange-600 rounded-lg px-3 py-1.5 transition">
                Abrir cozinha
            </a>
        </div>
    </div>

    @php
    $dotColors = [
        'yellow' => 'bg-yellow-400',
        'blue'   => 'bg-blue-400',
        'orange' => 'bg-orange-400',
        'green'  => 'bg-green-400',
        'indigo' => 'bg-indigo-400',
    ];
    $badgeColors = [
        'yellow' => 'bg-yellow-400/10 text-yellow-400',
        'blue'   => 'bg-blue-400/10 text-blue-400',
        'orange' => 'bg-orange-400/10 text-orange-400',
        'green'  => 'bg-green-400/10 text-green-400',
        'indigo' => 'bg-indigo-400/10 text-indigo-400',
    ];
    @endphp

    {{-- Status summary --}}
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
        @foreach($this->buckets as $bucket)
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3 text-center">
            <p class="text-2xl font-bold text-white tabular-nums">
                {{ $this->countByStatus[$bucket['status']->value] ?? 0 }}
            </p>
            <p class="text-xs text-zinc-400 mt-1">{{ $bucket['label'] }}</p>
        </div>
        @endforeach
    </div>

    @if($this->orders->isEmpty())
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-5 py-16 text-center">
        <p class="text-zinc-500 text-sm">Nenhum pedido em andamento no momento.</p>
    </div>
    @else
    <div class="space-y-2">
        @foreach($this->orders as $order)
        @php
            $color   = collect($this->buckets)->firstWhere('status', $order->status)['color'] ?? 'gray';
            $minutes = $order->created_at->diffInMinutes(now());
            $late    = $minutes > 30;
        @endphp
        <a href="{{ route('admin.orders.show', $order) }}" wire:navigate
           class="flex items-center justify-between bg-zinc-900 border {{ $late ? 'border-red-500/30' : 'border-zinc-800' }} rounded-xl px-5 py-4 hover:bg-zinc-800/40 transition">
            <div class="flex items-center gap-4">
                <span class="text-xs font-mono text-zinc-500">#{{ $order->number }}</span>
                <div>
                    <p class="text-sm font-medium text-white">{{ $order->customer_name ?: '—' }}</p>
                    <p class="text-xs text-zinc-500">
                        {{ $order->delivery_type === DeliveryType::Delivery ? 'Delivery' : 'Balcão' }}
                        · há {{ $minutes }} min
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm text-zinc-200 tabular-nums font-medium">
                    R$ {{ number_format($order->total, 2, ',', '.') }}
                </span>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeColors[$color] ?? 'bg-zinc-700 text-zinc-400' }}">
                    <span class="size-1.5 rounded-full mr-1.5 {{ $dotColors[$color] ?? 'bg-zinc-500' }}"></span>
                    {{ $order->status->label() }}
                </span>
                @if($late)
                <span class="text-xs text-red-400">atrasado</span>
                @endif
            </div>
        </a>
        @endforeach
    </div>
    @endif
</div>
