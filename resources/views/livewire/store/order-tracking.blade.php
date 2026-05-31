<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Polling;
use Livewire\Volt\Component;
use App\Enums\OrderStatus;
use App\Models\Order;

new #[Layout('components.layouts.customer')] class extends Component {

    public string $orderNumber = '';

    public function mount(string $order): void
    {
        $this->orderNumber = $order;
    }

    #[Computed]
    #[Polling(30000)]
    public function order(): ?Order
    {
        return Order::with(['items', 'statusHistory'])
            ->where('number', $this->orderNumber)
            ->first();
    }

    public function statusSteps(): array
    {
        return [
            ['status' => OrderStatus::PendingConfirmation, 'label' => 'Pedido recebido',    'icon' => 'clock'],
            ['status' => OrderStatus::Confirmed,            'label' => 'Confirmado',         'icon' => 'check'],
            ['status' => OrderStatus::InPreparation,        'label' => 'Em preparo',         'icon' => 'fire'],
            ['status' => OrderStatus::ReadyForPickup,       'label' => 'Pronto',             'icon' => 'bell'],
        ];
    }
}; ?>

<div>
    @if(! $this->order)
    <div class="py-20 text-center">
        <p class="text-zinc-500">Pedido #{{ $orderNumber }} não encontrado.</p>
        <a href="{{ route('store.menu') }}" wire:navigate class="inline-block mt-3 text-sm text-orange-500 hover:text-orange-600">
            Ver cardápio →
        </a>
    </div>
    @else
    @php $order = $this->order; @endphp

    <div class="mb-6">
        <p class="text-sm text-zinc-400">Pedido</p>
        <h1 class="text-2xl font-bold text-zinc-800">#{{ $order->number }}</h1>
    </div>

    {{-- Status badge --}}
    @php
    $colors = [
        'yellow' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
        'blue'   => 'bg-blue-50 text-blue-700 border-blue-200',
        'orange' => 'bg-orange-50 text-orange-700 border-orange-200',
        'purple' => 'bg-purple-50 text-purple-700 border-purple-200',
        'green'  => 'bg-green-50 text-green-700 border-green-200',
        'red'    => 'bg-red-50 text-red-700 border-red-200',
        'gray'   => 'bg-zinc-100 text-zinc-600 border-zinc-200',
    ];
    $colorClass = $colors[$order->status->color()] ?? $colors['gray'];
    @endphp

    <div class="inline-flex items-center border rounded-full px-3 py-1 text-sm font-medium mb-6 {{ $colorClass }}">
        {{ $order->status->label() }}
    </div>

    {{-- Progress steps --}}
    @if(! $order->status->isFinal())
    <div class="bg-white border border-zinc-200 rounded-2xl p-5 mb-5">
        <div class="flex items-center">
            @foreach($this->statusSteps() as $i => $step)
            @php
            $statuses = array_column($this->statusSteps(), 'status');
            $currentIdx = array_search($order->status, $statuses);
            $stepIdx = $i;
            $done = $currentIdx !== false && $stepIdx <= $currentIdx;
            $active = $currentIdx !== false && $stepIdx === $currentIdx;
            @endphp

            <div class="flex flex-col items-center {{ $i < count($this->statusSteps()) - 1 ? 'flex-1' : '' }}">
                <div class="size-8 rounded-full flex items-center justify-center text-xs font-bold transition
                    {{ $done ? 'bg-orange-500 text-white' : 'bg-zinc-100 text-zinc-400' }}
                    {{ $active ? 'ring-2 ring-orange-300 ring-offset-1' : '' }}">
                    @if($done && ! $active)
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    @else
                    {{ $i + 1 }}
                    @endif
                </div>
                <p class="text-[10px] text-zinc-500 mt-1 text-center leading-tight max-w-[60px]">{{ $step['label'] }}</p>
            </div>

            @if($i < count($this->statusSteps()) - 1)
            <div class="flex-1 h-0.5 mb-5 mx-1 {{ $done && $stepIdx < $currentIdx ? 'bg-orange-400' : 'bg-zinc-200' }}"></div>
            @endif
            @endforeach
        </div>
    </div>
    @endif

    {{-- Order items --}}
    <div class="bg-white border border-zinc-200 rounded-2xl p-5 mb-4">
        <h2 class="text-sm font-semibold text-zinc-700 mb-3">Itens do pedido</h2>
        <div class="space-y-2">
            @foreach($order->items as $item)
            <div class="flex justify-between text-sm">
                <span class="text-zinc-700">{{ $item->quantity }}× {{ $item->product_name }}</span>
                <span class="text-zinc-600 tabular-nums">R$ {{ number_format($item->subtotal, 2, ',', '.') }}</span>
            </div>
            @endforeach
        </div>

        <div class="mt-3 pt-3 border-t border-zinc-100 space-y-1 text-sm">
            <div class="flex justify-between text-zinc-500">
                <span>Subtotal</span>
                <span class="tabular-nums">R$ {{ number_format($order->subtotal, 2, ',', '.') }}</span>
            </div>
            @if($order->delivery_fee > 0)
            <div class="flex justify-between text-zinc-500">
                <span>Taxa de entrega</span>
                <span class="tabular-nums">R$ {{ number_format($order->delivery_fee, 2, ',', '.') }}</span>
            </div>
            @endif
            <div class="flex justify-between font-bold text-zinc-800 pt-2 border-t border-zinc-100">
                <span>Total</span>
                <span class="tabular-nums">R$ {{ number_format($order->total, 2, ',', '.') }}</span>
            </div>
        </div>
    </div>

    {{-- Delivery info --}}
    <div class="bg-white border border-zinc-200 rounded-2xl p-5 text-sm text-zinc-600">
        <h2 class="text-sm font-semibold text-zinc-700 mb-2">Detalhes</h2>
        <p><span class="text-zinc-400">Tipo: </span>{{ $order->delivery_type->label() }}</p>
        @if($order->table_number)
        <p><span class="text-zinc-400">Mesa: </span>{{ $order->table_number }}</p>
        @endif
        @if($order->delivery_address_street)
        <p class="mt-1"><span class="text-zinc-400">Endereço: </span>
            {{ $order->delivery_address_street }}, {{ $order->delivery_address_number }}
            @if($order->delivery_address_complement), {{ $order->delivery_address_complement }}@endif
            — {{ $order->delivery_address_city }}/{{ $order->delivery_address_state }}
        </p>
        @endif
        @if($order->notes)
        <p class="mt-1"><span class="text-zinc-400">Obs: </span>{{ $order->notes }}</p>
        @endif
    </div>

    <p class="text-center text-xs text-zinc-400 mt-5">Atualizado a cada 30 segundos</p>
    @endif
</div>
