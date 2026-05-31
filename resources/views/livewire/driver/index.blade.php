<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Poll;
use Livewire\Volt\Component;
use App\Actions\Orders\TransitionOrderStatus;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Restaurant;

new #[Layout('components.layouts.app')] #[Poll(30000)] class extends Component {

    public ?string $error = null;

    private function rid(): ?int
    {
        return Restaurant::query()->value('id');
    }

    #[Computed]
    public function readyForPickup()
    {
        return Order::where('restaurant_id', $this->rid())
            ->where('status', OrderStatus::ReadyForPickup->value)
            ->where('delivery_type', DeliveryType::Delivery->value)
            ->with('items')
            ->orderBy('ready_at')
            ->get();
    }

    #[Computed]
    public function outForDelivery()
    {
        return Order::where('restaurant_id', $this->rid())
            ->where('status', OrderStatus::OutForDelivery->value)
            ->with('items')
            ->orderBy('updated_at')
            ->get();
    }

    public function pickUp(int $orderId): void
    {
        $this->error = null;

        try {
            $order = Order::where('restaurant_id', $this->rid())->findOrFail($orderId);
            app(TransitionOrderStatus::class)->execute($order, OrderStatus::OutForDelivery, auth()->user());
            unset($this->readyForPickup, $this->outForDelivery);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function markDelivered(int $orderId): void
    {
        $this->error = null;

        try {
            $order = Order::where('restaurant_id', $this->rid())->findOrFail($orderId);
            app(TransitionOrderStatus::class)->execute($order, OrderStatus::Delivered, auth()->user());
            unset($this->readyForPickup, $this->outForDelivery);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Entregas</h1>
            <p class="text-sm text-zinc-400 mt-0.5">
                {{ $this->readyForPickup->count() }} aguardando retirada ·
                {{ $this->outForDelivery->count() }} em rota
            </p>
        </div>
        <span class="inline-flex items-center gap-1.5 text-xs text-orange-400 bg-orange-400/10 border border-orange-400/20 rounded-lg px-3 py-1.5">
            <span class="size-1.5 rounded-full bg-orange-400 animate-pulse"></span>
            Atualiza a cada 30s
        </span>
    </div>

    @if($error)
    <div class="mb-5 px-4 py-3 bg-red-500/10 border border-red-500/20 rounded-xl text-sm text-red-400">
        {{ $error }}
    </div>
    @endif

    {{-- Em rota --}}
    @if($this->outForDelivery->isNotEmpty())
    <div class="mb-6">
        <h2 class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-3">Em rota</h2>
        <div class="space-y-3">
            @foreach($this->outForDelivery as $order)
            <div class="bg-zinc-900 border border-indigo-500/30 rounded-xl p-4">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-mono text-zinc-500">#{{ $order->number }}</span>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-indigo-400/10 text-indigo-400">
                                Em rota
                            </span>
                        </div>
                        <p class="text-sm font-semibold text-white">{{ $order->customer_name }}</p>
                        @if($order->customer_phone)
                        <p class="text-xs text-zinc-400 mt-0.5">{{ $order->customer_phone }}</p>
                        @endif
                    </div>
                    <p class="text-sm font-bold text-zinc-200 tabular-nums shrink-0">
                        R$ {{ number_format($order->total, 2, ',', '.') }}
                    </p>
                </div>

                @if($order->delivery_address_street)
                <div class="bg-zinc-800 rounded-lg px-3 py-2 mb-3 text-xs text-zinc-300">
                    <p>{{ $order->delivery_address_street }}, {{ $order->delivery_address_number }}
                    @if($order->delivery_address_complement) – {{ $order->delivery_address_complement }}@endif</p>
                    <p class="text-zinc-500 mt-0.5">{{ $order->delivery_address_neighborhood }} · {{ $order->delivery_address_city }}/{{ $order->delivery_address_state }}</p>
                </div>
                @endif

                <div class="flex gap-2">
                    <button wire:click="markDelivered({{ $order->id }})"
                            wire:confirm="Confirmar entrega do pedido #{{ $order->number }}?"
                            wire:loading.attr="disabled"
                            class="flex-1 py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition">
                        <span wire:loading.remove wire:target="markDelivered({{ $order->id }})">Confirmar entrega</span>
                        <span wire:loading wire:target="markDelivered({{ $order->id }})">Confirmando…</span>
                    </button>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Prontos para retirada --}}
    <div>
        <h2 class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-3">Prontos para retirada</h2>

        @if($this->readyForPickup->isEmpty())
        <div class="bg-zinc-900 border border-zinc-800 border-dashed rounded-xl px-5 py-12 text-center">
            <p class="text-zinc-500 text-sm">Nenhum pedido aguardando retirada.</p>
        </div>
        @else
        <div class="space-y-3">
            @foreach($this->readyForPickup as $order)
            <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-4">
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-mono text-zinc-500">#{{ $order->number }}</span>
                            @if($order->ready_at)
                            <span class="text-xs text-zinc-500">pronto {{ $order->ready_at->diffForHumans() }}</span>
                            @endif
                        </div>
                        <p class="text-sm font-semibold text-white">{{ $order->customer_name }}</p>
                        @if($order->customer_phone)
                        <p class="text-xs text-zinc-400 mt-0.5">{{ $order->customer_phone }}</p>
                        @endif
                    </div>
                    <p class="text-sm font-bold text-zinc-200 tabular-nums shrink-0">
                        R$ {{ number_format($order->total, 2, ',', '.') }}
                    </p>
                </div>

                {{-- Items summary --}}
                <div class="bg-zinc-800 rounded-lg px-3 py-2 mb-3">
                    @foreach($order->items->take(3) as $item)
                    <p class="text-xs text-zinc-300">{{ $item->quantity }}× {{ $item->product_name }}</p>
                    @endforeach
                    @if($order->items->count() > 3)
                    <p class="text-xs text-zinc-500 mt-0.5">+{{ $order->items->count() - 3 }} itens</p>
                    @endif
                </div>

                @if($order->delivery_address_street)
                <div class="bg-zinc-800/60 rounded-lg px-3 py-2 mb-3 text-xs text-zinc-400">
                    <p>{{ $order->delivery_address_street }}, {{ $order->delivery_address_number }}
                    @if($order->delivery_address_complement) – {{ $order->delivery_address_complement }}@endif</p>
                    <p class="text-zinc-500 mt-0.5">{{ $order->delivery_address_neighborhood }} · {{ $order->delivery_address_city }}/{{ $order->delivery_address_state }}</p>
                </div>
                @endif

                <button wire:click="pickUp({{ $order->id }})"
                        wire:loading.attr="disabled"
                        class="w-full py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl transition">
                    <span wire:loading.remove wire:target="pickUp({{ $order->id }})">Pegar para entrega</span>
                    <span wire:loading wire:target="pickUp({{ $order->id }})">Pegando…</span>
                </button>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>
