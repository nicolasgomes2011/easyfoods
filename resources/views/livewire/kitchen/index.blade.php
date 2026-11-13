<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Session;
use Livewire\Volt\Component;
use App\Enums\DeliveryType;
use App\Enums\OrderItemChangeType;
use App\Enums\OrderStatus;
use App\Enums\WaiterCallStatus;
use App\Livewire\Concerns\AlertsNewOrders;
use App\Models\Order;
use App\Support\CurrentRestaurant;
use App\Models\WaiterCall;
use App\Actions\Orders\TransitionOrderStatus;
use Illuminate\Support\Facades\Gate;

new #[Layout('components.layouts.app')] class extends Component {
    use AlertsNewOrders;

    /** Kitchen alert: a confirmed order waits for preparation. */
    protected function alertStatus(): OrderStatus
    {
        return OrderStatus::Confirmed;
    }

    /** Order type shown on the queue ('all' or a DeliveryType value); kept in the user's session. */
    #[Session]
    public string $typeFilter = 'all';

    /** Filter buttons: value => label. */
    #[Computed]
    public function filters(): array
    {
        return ['all' => 'Todos', 'dine_in' => 'Mesa', 'delivery' => 'Delivery', 'pickup' => 'Retirada'];
    }

    public function filterBy(string $type): void
    {
        $this->typeFilter = DeliveryType::tryFrom($type)?->value ?? 'all';
        unset($this->queueOrders, $this->waitingCount, $this->inPreparationCount, $this->readyCount, $this->hiddenByFilterCount);
    }

    /** Queue orders of other types while a filter is on: shown as a notice, never hidden silently. */
    #[Computed]
    public function hiddenByFilterCount(): int
    {
        $type = DeliveryType::tryFrom($this->typeFilter);

        if ($type === null) {
            return 0;
        }

        return Order::where('restaurant_id', CurrentRestaurant::id())
            ->whereIn('status', [OrderStatus::Confirmed->value, OrderStatus::InPreparation->value])
            ->where('delivery_type', '!=', $type->value)
            ->count();
    }

    /** This restaurant's orders of the selected type: the queue and the counters. */
    private function kitchenOrders()
    {
        return Order::where('restaurant_id', CurrentRestaurant::id())
            ->when(
                DeliveryType::tryFrom($this->typeFilter),
                fn ($query, DeliveryType $type) => $query->where('delivery_type', $type->value)
            );
    }

    #[Computed]
    public function waitingCount(): int
    {
        return $this->kitchenOrders()->byStatus(OrderStatus::Confirmed)->count();
    }

    #[Computed]
    public function inPreparationCount(): int
    {
        return $this->kitchenOrders()->byStatus(OrderStatus::InPreparation)->count();
    }

    #[Computed]
    public function readyCount(): int
    {
        return $this->kitchenOrders()->byStatus(OrderStatus::ReadyForPickup)->count();
    }

    #[Computed]
    public function queueOrders()
    {
        return $this->kitchenOrders()
            ->whereIn('status', [
                OrderStatus::Confirmed->value,
                OrderStatus::InPreparation->value,
            ])
            // Addons come with the items: the card lists them under each line.
            // Removals too: the card keeps a removed item struck through.
            ->with([
                'items.addons',
                'itemChanges' => fn ($query) => $query->where('type', OrderItemChangeType::Removed->value),
            ])
            ->orderBy('created_at')
            ->get();
    }

    public function startPreparing(int $orderId): void
    {
        $this->applyTransition($orderId, OrderStatus::InPreparation);
    }

    public function markReady(int $orderId): void
    {
        $this->applyTransition($orderId, OrderStatus::ReadyForPickup);
    }

    private function applyTransition(int $orderId, OrderStatus $to): void
    {
        $order = Order::findOrFail($orderId);

        Gate::authorize('transitionInKitchen', $order);

        app(TransitionOrderStatus::class)->execute($order, $to, auth()->user());

        unset($this->queueOrders, $this->waitingCount, $this->inPreparationCount, $this->readyCount, $this->hiddenByFilterCount);

        // Keep the tab title count in step with the queue right away, not on the next poll.
        $this->checkNewOrders();
    }

    #[Computed]
    public function pendingWaiterCalls()
    {
        $restaurant = CurrentRestaurant::get();
        if (! $restaurant) return collect();
        return WaiterCall::with('diningTable')
            ->where('restaurant_id', $restaurant->id)
            ->where('status', WaiterCallStatus::Pending->value)
            ->orderBy('called_at')
            ->get();
    }

    public function acknowledgeWaiterCall(int $callId): void
    {
        WaiterCall::where('id', $callId)->update([
            'status'          => WaiterCallStatus::Acknowledged->value,
            'acknowledged_at' => now(),
        ]);
        unset($this->pendingWaiterCalls);
    }
}; ?>

<div wire:poll.30s.keep-alive="checkNewOrders">
    {{-- Waiter call alerts --}}
    @if($this->pendingWaiterCalls->isNotEmpty())
    <div class="mb-5 space-y-2">
        @foreach($this->pendingWaiterCalls as $call)
        <div class="flex items-center justify-between bg-yellow-400/10 border border-yellow-400/30 rounded-xl px-4 py-3">
            <div class="flex items-center gap-2">
                <span class="size-2 rounded-full bg-yellow-400 animate-pulse"></span>
                <span class="text-sm font-medium text-yellow-300">
                    Mesa {{ $call->diningTable?->number ?? '?' }} está chamando o garçom
                </span>
                <span class="text-xs text-yellow-500">{{ $call->called_at->diffForHumans() }}</span>
            </div>
            <button wire:click="acknowledgeWaiterCall({{ $call->id }})"
                    class="min-h-12 text-sm font-semibold text-yellow-300 hover:text-yellow-200 bg-yellow-400/10 hover:bg-yellow-400/20 rounded-xl px-4 transition">
                Atender
            </button>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Cozinha</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Fila de preparo em tempo real</p>
        </div>
        <div class="flex items-center gap-2">
            <x-orders.new-order-alert :count="$alertCount" />
            <span class="inline-flex items-center gap-1.5 text-xs text-orange-400 bg-orange-400/10 border border-orange-400/20 rounded-lg px-3 py-1.5">
                <span class="size-1.5 rounded-full bg-orange-400 animate-pulse"></span>
                Em andamento · atualiza a cada 30s
            </span>
        </div>
    </div>

    {{-- Order type filter (kept in the session) --}}
    <div class="flex flex-wrap gap-2 mb-4" role="group" aria-label="Filtrar a fila por tipo de pedido">
        @foreach($this->filters as $value => $label)
        <button type="button"
                data-kitchen-filter="{{ $value }}"
                wire:click="filterBy('{{ $value }}')"
                aria-pressed="{{ $typeFilter === $value ? 'true' : 'false' }}"
                class="min-h-12 min-w-24 rounded-xl border px-4 text-base font-semibold transition {{ $typeFilter === $value ? 'border-orange-500 bg-orange-500/15 text-white' : 'border-zinc-700 bg-zinc-900 text-zinc-300 hover:border-zinc-500' }}">
            {{ $label }}
        </button>
        @endforeach
    </div>
    @if($this->hiddenByFilterCount > 0)
    <div data-kitchen-hidden class="flex items-center justify-between gap-3 mb-4 rounded-xl border border-yellow-400/30 bg-yellow-400/10 px-4 py-2">
        <span class="text-sm text-yellow-200">
            {{ $this->hiddenByFilterCount }} {{ $this->hiddenByFilterCount === 1 ? 'pedido de outro tipo' : 'pedidos de outros tipos' }} na fila
        </span>
        <button type="button" wire:click="filterBy('all')"
                class="min-h-12 text-sm font-semibold text-yellow-200 hover:text-white rounded-xl px-4 transition">
            Ver todos
        </button>
    </div>
    @endif

    {{-- Status summary: this restaurant, selected type --}}
    <div class="grid grid-cols-3 gap-3 mb-6">
        @foreach([
            ['key' => 'waiting',        'label' => 'Aguardando preparo', 'value' => $this->waitingCount],
            ['key' => 'in_preparation', 'label' => 'Em preparo',          'value' => $this->inPreparationCount],
            ['key' => 'ready',          'label' => 'Prontos',             'value' => $this->readyCount],
        ] as $stat)
        <div data-kitchen-count="{{ $stat['key'] }}" class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3 text-center">
            <p class="text-3xl font-bold text-white tabular-nums">{{ $stat['value'] }}</p>
            <p class="text-sm text-zinc-400 mt-1">{{ $stat['label'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Queue: waiting first, then in preparation --}}
    @if($this->queueOrders->isEmpty())
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-5 py-12 text-center">
        <p class="text-zinc-500 text-base">
            @if($typeFilter === 'all')
            Fila vazia. Nenhum pedido aguardando preparo.
            @else
            Nenhum pedido de {{ mb_strtolower($this->filters[$typeFilter]) }} na fila.
            @endif
        </p>
    </div>
    @else
    <div class="space-y-6">
        @foreach([
            ['status' => OrderStatus::Confirmed,     'label' => 'Aguardando preparo', 'empty' => 'Nenhum pedido aguardando preparo.'],
            ['status' => OrderStatus::InPreparation, 'label' => 'Em preparo',         'empty' => 'Nenhum pedido em preparo.'],
        ] as $group)
        @php $groupOrders = $this->queueOrders->filter(fn ($order) => $order->status === $group['status']); @endphp
        <section data-kitchen-group="{{ $group['status']->value }}">
            <h2 class="text-base font-semibold text-zinc-200 mb-3">
                {{ $group['label'] }} <span class="text-zinc-500 tabular-nums">· {{ $groupOrders->count() }}</span>
            </h2>
            @if($groupOrders->isEmpty())
            <p class="text-sm text-zinc-500 bg-zinc-900 border border-dashed border-zinc-800 rounded-xl px-4 py-4">{{ $group['empty'] }}</p>
            @else
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach($groupOrders as $order)
                <x-kitchen.order-card :order="$order" wire:key="kitchen-order-{{ $order->id }}" />
                @endforeach
            </div>
            @endif
        </section>
        @endforeach
    </div>
    @endif
</div>
