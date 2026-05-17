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
    public function waitingCount(): int
    {
        return Order::byStatus(OrderStatus::Confirmed)->count();
    }

    #[Computed]
    public function inPreparationCount(): int
    {
        return Order::byStatus(OrderStatus::InPreparation)->count();
    }

    #[Computed]
    public function readyCount(): int
    {
        return Order::byStatus(OrderStatus::ReadyForPickup)->count();
    }

    #[Computed]
    public function queueOrders()
    {
        return Order::whereIn('status', [
            OrderStatus::Confirmed->value,
            OrderStatus::InPreparation->value,
        ])
            ->with('items')
            ->orderBy('created_at')
            ->get();
    }
}; ?>

<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Cozinha</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Fila de preparo em tempo real</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 text-xs text-orange-400 bg-orange-400/10 border border-orange-400/20 rounded-lg px-3 py-1.5">
                <span class="size-1.5 rounded-full bg-orange-400 animate-pulse"></span>
                Em andamento · atualiza a cada 30s
            </span>
        </div>
    </div>

    {{-- Status summary --}}
    <div class="grid grid-cols-3 gap-3 mb-6">
        @foreach([
            ['label' => 'Aguardando preparo', 'value' => $this->waitingCount],
            ['label' => 'Em preparo',          'value' => $this->inPreparationCount],
            ['label' => 'Prontos',             'value' => $this->readyCount],
        ] as $stat)
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3 text-center">
            <p class="text-2xl font-bold text-white tabular-nums">{{ $stat['value'] }}</p>
            <p class="text-xs text-zinc-400 mt-1">{{ $stat['label'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Queue cards --}}
    @if($this->queueOrders->isEmpty())
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-5 py-12 text-center">
        <p class="text-zinc-500 text-sm">Fila vazia. Nenhum pedido aguardando preparo.</p>
    </div>
    @else
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach($this->queueOrders as $order)
        @php
            $inPrep  = $order->status === OrderStatus::InPreparation;
            $minutes = $order->created_at->diffInMinutes(now());
            $urgent  = $inPrep && $minutes >= 20;
        @endphp
        <div class="bg-zinc-900 border {{ $inPrep || $urgent ? 'border-orange-500/50' : 'border-zinc-800' }} rounded-xl overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b {{ $inPrep || $urgent ? 'border-orange-500/30 bg-orange-500/5' : 'border-zinc-800' }}">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-bold font-mono {{ $inPrep || $urgent ? 'text-orange-400' : 'text-white' }}">
                        #{{ $order->number }}
                    </span>
                    <span class="text-xs text-zinc-400">
                        {{ $order->delivery_type === DeliveryType::Delivery ? 'Delivery' : 'Balcão' }}
                    </span>
                    @if($inPrep)
                    <span class="text-[10px] font-semibold text-orange-400 bg-orange-400/10 border border-orange-400/20 rounded-full px-1.5 py-0.5">
                        Em preparo
                    </span>
                    @else
                    <span class="text-[10px] font-semibold text-yellow-400 bg-yellow-400/10 border border-yellow-400/20 rounded-full px-1.5 py-0.5">
                        Aguardando
                    </span>
                    @endif
                </div>
                <div class="flex items-center gap-1.5 text-xs {{ $urgent ? 'text-red-400' : 'text-zinc-400' }}">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ $minutes }} min
                </div>
            </div>
            <div class="px-4 py-3 space-y-1.5">
                @foreach($order->items as $item)
                <div class="flex items-center gap-2">
                    <span class="size-1.5 rounded-full bg-zinc-600 shrink-0"></span>
                    <span class="text-sm text-zinc-300">{{ $item->displayName() }} ×{{ $item->quantity }}</span>
                </div>
                @endforeach
            </div>
            <div class="px-4 py-3 border-t border-zinc-800 flex items-center justify-between">
                <span class="text-xs text-zinc-500 tabular-nums">
                    R$ {{ number_format($order->total, 2, ',', '.') }}
                </span>
                <span class="text-xs text-zinc-600 italic">ações em breve</span>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
