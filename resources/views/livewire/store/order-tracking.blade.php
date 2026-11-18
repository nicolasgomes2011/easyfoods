<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Support\ReadyEstimate;
use Carbon\CarbonInterface;

new #[Layout('components.layouts.customer')] class extends Component {

    public string $token = '';

    public function mount(string $order): void
    {
        // The {order} route param carries the order's public token (uuid),
        // not the sequential number — sequential numbers are enumerable and
        // would let anyone read another customer's order. See orders.token.
        $this->token = $order;
    }

    #[Computed]
    public function order(): ?Order
    {
        return Order::with(['items.addons', 'statusHistory'])
            ->where('token', $this->token)
            ->first();
    }

    /**
     * Customer timeline for the order's delivery type. Each step says whether it
     * is done, current or pending, and the HH:MM it was reached (from the
     * append-only status history).
     *
     * @return list<array{status: string, label: string, state: string, time: ?string}>
     */
    public function steps(Order $order): array
    {
        $labels = [
            OrderStatus::PendingConfirmation->value => 'Pedido recebido',
            OrderStatus::Confirmed->value           => 'Confirmado',
            OrderStatus::InPreparation->value       => 'Em preparo',
        ] + match ($order->delivery_type) {
            DeliveryType::Delivery => [
                OrderStatus::ReadyForPickup->value => 'Pronto',
                OrderStatus::OutForDelivery->value => 'Saiu para entrega',
                OrderStatus::Delivered->value      => 'Entregue',
            ],
            DeliveryType::Pickup => [
                OrderStatus::ReadyForPickup->value => 'Pronto para retirada',
                OrderStatus::Completed->value      => 'Retirado',
            ],
            DeliveryType::DineIn => [
                OrderStatus::ReadyForPickup->value => 'Pronto',
                OrderStatus::Completed->value      => 'Servido',
            ],
        };

        // Lifecycle order: the current step is the last one the order has reached.
        $rank = array_flip(array_map(fn (OrderStatus $s) => $s->value, [
            OrderStatus::PendingConfirmation, OrderStatus::Confirmed, OrderStatus::InPreparation,
            OrderStatus::ReadyForPickup, OrderStatus::OutForDelivery, OrderStatus::Delivered,
            OrderStatus::Completed,
        ]));
        $reached = $rank[$order->status->value] ?? -1;
        $keys = array_keys($labels);
        $currentIdx = -1;
        foreach ($keys as $idx => $status) {
            if ($rank[$status] <= $reached) {
                $currentIdx = $idx;
            }
        }
        $lastIdx = count($keys) - 1;

        $steps = [];
        foreach ($keys as $idx => $status) {
            $state = match (true) {
                $idx < $currentIdx => 'done',
                $idx === $currentIdx => ($order->status->isFinal() || $idx === $lastIdx) ? 'done' : 'current',
                default => 'pending',
            };

            $at = $order->statusHistory->firstWhere('to_status', OrderStatus::from($status))?->changed_at
                ?? ($status === OrderStatus::PendingConfirmation->value ? $order->created_at : null);

            $steps[] = [
                'status' => $status,
                'label'  => $labels[$status],
                'state'  => $state,
                'time'   => $state !== 'pending' ? $at?->format('H:i') : null,
            ];
        }

        return $steps;
    }

    /**
     * "Pronto às" clock time while the kitchen works on the order. An overdue
     * estimate is pushed forward here (at most twice, never backward).
     */
    public function readyAt(Order $order): ?CarbonInterface
    {
        if (! in_array($order->status, [OrderStatus::Confirmed, OrderStatus::InPreparation], true)) {
            return null;
        }

        ReadyEstimate::adjust($order);

        return $order->estimated_ready_at;
    }

    /** The cancel entry of the history (reason in `notes`), if any. */
    public function cancellation(Order $order): ?OrderStatusHistory
    {
        return $order->statusHistory->firstWhere('to_status', OrderStatus::Canceled);
    }
}; ?>

{{-- Keep refreshing until the order reaches a final status. --}}
<div @if($this->order && ! $this->order->status->isFinal()) wire:poll.10s @endif>
    @if(! $this->order)
    <div class="py-20 text-center">
        <p class="text-zinc-500">Pedido não encontrado.</p>
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

    {{-- Ready time as a clock time; it only ever moves forward, with a delay message --}}
    @if($readyAt = $this->readyAt($order))
    <div data-ready-at class="bg-orange-50 border border-orange-200 rounded-2xl px-5 py-4 mb-5 text-sm font-semibold text-orange-700">
        @if($order->eta_adjustments > 0)
        Está demorando um pouquinho mais, novo horário: {{ $readyAt->format('H:i') }}
        @else
        Pronto às {{ $readyAt->format('H:i') }}
        @endif
    </div>
    @endif

    @if($order->status === OrderStatus::Canceled)
    {{-- Canceled: say when and why instead of the timeline --}}
    @php $cancel = $this->cancellation($order); $canceledAt = $cancel?->changed_at ?? $order->canceled_at; @endphp
    <div class="bg-red-50 border border-red-200 rounded-2xl p-5 mb-5 text-sm">
        <p class="font-semibold text-red-700">
            Pedido cancelado {{ $canceledAt ? 'às '.$canceledAt->format('H:i') : '' }}
        </p>
        @if($cancel?->notes)
        <p class="text-red-600 mt-1"><span class="text-red-400">Motivo: </span>{{ $cancel->notes }}</p>
        @endif
    </div>
    @else
    {{-- Progress steps (by delivery type), each with the time it was reached --}}
    @php $steps = $this->steps($order); $last = count($steps) - 1; @endphp
    <div class="bg-white border border-zinc-200 rounded-2xl p-5 mb-5">
        <div class="flex items-start">
            @foreach($steps as $i => $step)
            @php $done = $step['state'] !== 'pending'; $active = $step['state'] === 'current'; @endphp

            <div data-step="{{ $step['status'] }}" data-state="{{ $step['state'] }}" class="flex flex-col items-center">
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
                @if($step['time'])
                <p class="text-[10px] text-zinc-400 tabular-nums">{{ $step['time'] }}</p>
                @endif
            </div>

            @if($i < $last)
            <div class="flex-1 h-0.5 mt-4 mx-1 {{ $step['state'] === 'done' && $steps[$i + 1]['state'] !== 'pending' ? 'bg-orange-400' : 'bg-zinc-200' }}"></div>
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
                <span class="text-zinc-700">
                    {{ $item->quantity }}× {{ $item->displayName() }}
                    @if($item->addons->isNotEmpty())
                    <span class="block text-xs text-zinc-400">{{ $item->addons->map(fn ($a) => $a->addon_option_name)->filter()->join(', ') }}</span>
                    @endif
                </span>
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
