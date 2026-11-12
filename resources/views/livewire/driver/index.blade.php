<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use App\Actions\Delivery\AcceptDelivery;
use App\Actions\Delivery\DeclineDelivery;
use App\Actions\Orders\TransitionOrderStatus;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\CurrentRestaurant;
use Illuminate\Validation\ValidationException;

/*
 * Driver panel. A delivery ready to leave is under "Disponíveis" until a driver
 * accepts it (orders.driver_id); from then on it is under that driver's
 * "Minhas entregas", and only that driver picks it up and marks it delivered.
 */
new #[Layout('components.layouts.app')] class extends Component {

    public ?string $error = null;

    private function rid(): ?int
    {
        return CurrentRestaurant::id();
    }

    /** This driver's deliveries of the restaurant in $status. */
    private function mine(OrderStatus $status)
    {
        return Order::where('restaurant_id', $this->rid())
            ->where('delivery_type', DeliveryType::Delivery->value)
            ->where('driver_id', auth()->id())
            ->where('status', $status->value);
    }

    /** Accepted, waiting to be picked up at the counter. */
    #[Computed]
    public function myPickups()
    {
        return $this->mine(OrderStatus::ReadyForPickup)
            ->with('items')
            ->orderBy('ready_at')
            ->get();
    }

    #[Computed]
    public function myEnRoute()
    {
        return $this->mine(OrderStatus::OutForDelivery)
            ->orderBy('out_for_delivery_at') // left first, listed first
            ->orderBy('updated_at')          // tie-break for orders without the milestone
            ->get();
    }

    /** Deliveries nobody owns yet, minus the ones this driver declined. */
    #[Computed]
    public function available()
    {
        return Order::availableForDelivery($this->rid())
            ->whereDoesntHave('deliveryDeclines', fn ($query) => $query->where('user_id', auth()->id()))
            ->with('items')
            ->orderBy('ready_at')
            ->get();
    }

    public function accept(int $orderId): void
    {
        $this->act(fn () => app(AcceptDelivery::class)->execute($this->rid(), $orderId, auth()->user()));
    }

    public function decline(int $orderId): void
    {
        $this->act(fn () => app(DeclineDelivery::class)->execute($this->rid(), $orderId, auth()->user()));
    }

    public function pickUp(int $orderId): void
    {
        $this->act(fn () => app(TransitionOrderStatus::class)
            ->execute($this->ownOrder($orderId), OrderStatus::OutForDelivery, auth()->user()));
    }

    public function markDelivered(int $orderId): void
    {
        $this->act(fn () => app(TransitionOrderStatus::class)
            ->execute($this->ownOrder($orderId), OrderStatus::Delivered, auth()->user()));
    }

    /** Only the driver who accepted the delivery may move it (same restaurant). */
    private function ownOrder(int $orderId): Order
    {
        $order = Order::where('restaurant_id', $this->rid())
            ->where('driver_id', auth()->id())
            ->find($orderId);

        if ($order === null) {
            throw ValidationException::withMessages([
                'order' => 'Esta entrega não é sua. Aceite o pedido em "Disponíveis" antes.',
            ]);
        }

        return $order;
    }

    private function act(callable $action): void
    {
        $this->error = null;

        try {
            $action();
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }

        unset($this->myPickups, $this->myEnRoute, $this->available);
    }
}; ?>

<div wire:poll.30s>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Entregas</h1>
            <p class="text-sm text-zinc-400 mt-0.5">
                {{ $this->myPickups->count() + $this->myEnRoute->count() }} minhas ·
                {{ $this->available->count() }} disponíveis
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

    {{-- Minhas entregas: en route first, then accepted and waiting at the counter --}}
    <div class="mb-6">
        <h2 class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-3">Minhas entregas</h2>

        @if($this->myEnRoute->isEmpty() && $this->myPickups->isEmpty())
        <div class="bg-zinc-900 border border-zinc-800 border-dashed rounded-xl px-5 py-8 text-center">
            <p class="text-zinc-500 text-sm">Você não tem entregas agora. Aceite um pedido em "Disponíveis".</p>
        </div>
        @else
        <div class="space-y-3">
            @foreach($this->myEnRoute as $order)
            <x-driver.order-card :order="$order" accent="border-indigo-500/30" wire:key="en-route-{{ $order->id }}">
                <x-slot:badge>
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-indigo-400/10 text-indigo-400">Em rota</span>
                </x-slot:badge>

                <button wire:click="markDelivered({{ $order->id }})"
                        wire:confirm="Confirmar entrega do pedido #{{ $order->number }}?"
                        wire:loading.attr="disabled"
                        class="w-full py-2.5 bg-teal-500 hover:bg-teal-600 text-white text-sm font-semibold rounded-xl transition">
                    <span wire:loading.remove wire:target="markDelivered({{ $order->id }})">Confirmar entrega</span>
                    <span wire:loading wire:target="markDelivered({{ $order->id }})">Confirmando…</span>
                </button>
            </x-driver.order-card>
            @endforeach

            @foreach($this->myPickups as $order)
            <x-driver.order-card :order="$order" accent="border-orange-500/30" :items="true" wire:key="pickup-{{ $order->id }}">
                <x-slot:badge>
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-orange-400/10 text-orange-400">Aceito · retirar no balcão</span>
                </x-slot:badge>

                <button wire:click="pickUp({{ $order->id }})"
                        wire:loading.attr="disabled"
                        class="w-full py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl transition">
                    <span wire:loading.remove wire:target="pickUp({{ $order->id }})">Retirar pedido</span>
                    <span wire:loading wire:target="pickUp({{ $order->id }})">Retirando…</span>
                </button>
            </x-driver.order-card>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Disponíveis: nobody owns them yet --}}
    <div>
        <h2 class="text-xs font-semibold text-zinc-500 uppercase tracking-wider mb-3">Disponíveis</h2>

        @if($this->available->isEmpty())
        <div class="bg-zinc-900 border border-zinc-800 border-dashed rounded-xl px-5 py-12 text-center">
            <p class="text-zinc-500 text-sm">Nenhum pedido disponível para entrega.</p>
        </div>
        @else
        <div class="space-y-3">
            @foreach($this->available as $order)
            <x-driver.order-card :order="$order" :items="true" wire:key="available-{{ $order->id }}">
                <x-slot:badge>
                    @if($order->status === OrderStatus::OutForDelivery)
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-indigo-400/10 text-indigo-400">Em rota sem entregador</span>
                    @elseif($order->ready_at)
                    <span class="text-xs text-zinc-500">pronto {{ $order->ready_at->diffForHumans() }}</span>
                    @endif
                </x-slot:badge>

                <div class="flex gap-2">
                    <button wire:click="decline({{ $order->id }})"
                            wire:confirm="Recusar o pedido #{{ $order->number }}? Ele continua disponível para os outros entregadores."
                            wire:loading.attr="disabled"
                            class="px-4 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-zinc-300 text-sm font-medium rounded-xl transition">
                        Recusar
                    </button>
                    <button wire:click="accept({{ $order->id }})"
                            wire:loading.attr="disabled"
                            class="flex-1 py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl transition">
                        <span wire:loading.remove wire:target="accept({{ $order->id }})">Aceitar</span>
                        <span wire:loading wire:target="accept({{ $order->id }})">Aceitando…</span>
                    </button>
                </div>
            </x-driver.order-card>
            @endforeach
        </div>
        @endif
    </div>
</div>
