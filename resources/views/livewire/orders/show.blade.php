<?php
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Actions\Orders\TransitionOrderStatus;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\Gate;

new #[Layout('components.layouts.app')] class extends Component {
    public Order $order;

    public bool $showCancelForm = false;
    public string $cancelReason = '';
    public ?string $statusMessage = null;

    public function mount(Order $order): void
    {
        $this->order = $order->load(['items.addons', 'statusHistory', 'payment', 'customer']);
    }

    public function confirm(TransitionOrderStatus $action): void
    {
        Gate::authorize('updateStatus', $this->order);

        $action->execute($this->order, OrderStatus::Confirmed, auth()->user());

        $this->reloadOrder();
        $this->statusMessage = 'Pedido confirmado.';
    }

    public function cancel(TransitionOrderStatus $action): void
    {
        Gate::authorize('cancel', $this->order);

        $this->validate(
            ['cancelReason' => ['required', 'string', 'min:3', 'max:500']],
            [],
            ['cancelReason' => 'motivo'],
        );

        $action->execute($this->order, OrderStatus::Canceled, auth()->user(), $this->cancelReason);

        $this->reset('showCancelForm', 'cancelReason');
        $this->reloadOrder();
        $this->statusMessage = 'Pedido cancelado.';
    }

    private function reloadOrder(): void
    {
        $this->order = $this->order->fresh(['items.addons', 'statusHistory', 'payment', 'customer']);
    }
}; ?>

<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.orders.index') }}" wire:navigate
               class="text-zinc-400 hover:text-white transition">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-xl font-bold text-white">#{{ $this->order->number }}</h1>
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
                    $badgeClass = $statusColors[$this->order->status->color()] ?? 'bg-zinc-700 text-zinc-400';
                    @endphp
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeClass }}">
                        {{ $this->order->status->label() }}
                    </span>
                </div>
                <p class="text-sm text-zinc-400 mt-0.5">
                    {{ $this->order->created_at->format('d/m/Y \à\s H:i') }}
                    · {{ $this->order->delivery_type === DeliveryType::Delivery ? 'Delivery' : 'Retirada no balcão' }}
                </p>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-2">
            @can('updateStatus', $this->order)
                @if($this->order->status === OrderStatus::PendingConfirmation)
                <button
                    wire:click="confirm"
                    wire:loading.attr="disabled"
                    wire:target="confirm"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-green-400 bg-green-400/10 hover:bg-green-400/15 border border-green-400/20 rounded-lg px-3.5 py-2 transition disabled:opacity-50">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Confirmar pedido
                </button>
                @endif
            @endcan

            @can('cancel', $this->order)
                @if($this->order->isCancelable())
                <button
                    wire:click="$toggle('showCancelForm')"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-red-400 bg-red-400/10 hover:bg-red-400/15 border border-red-400/20 rounded-lg px-3.5 py-2 transition">
                    Cancelar
                </button>
                @endif
            @endcan
        </div>
    </div>

    {{-- Feedback --}}
    @if($statusMessage)
    <div class="mb-4 rounded-xl border border-green-500/30 bg-green-500/10 px-4 py-3 text-sm text-green-300">
        {{ $statusMessage }}
    </div>
    @endif

    {{-- Cancel form --}}
    @if($showCancelForm)
    <div class="mb-6 rounded-xl border border-red-500/30 bg-red-500/5 p-5">
        <h2 class="text-sm font-semibold text-white mb-1">Cancelar pedido</h2>
        <p class="text-xs text-zinc-400 mb-3">Informe o motivo do cancelamento (obrigatório).</p>
        <textarea
            wire:model="cancelReason"
            rows="3"
            placeholder="Ex: cliente desistiu, produto em falta..."
            class="w-full bg-zinc-900 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-red-500"></textarea>
        @error('cancelReason')
        <p class="text-xs text-red-400 mt-1.5">{{ $message }}</p>
        @enderror
        <div class="flex items-center gap-2 mt-3">
            <button
                wire:click="cancel"
                wire:loading.attr="disabled"
                wire:target="cancel"
                class="inline-flex items-center text-sm font-medium text-white bg-red-600 hover:bg-red-500 rounded-lg px-3.5 py-2 transition disabled:opacity-50">
                Confirmar cancelamento
            </button>
            <button
                type="button"
                wire:click="$set('showCancelForm', false)"
                class="text-sm text-zinc-400 hover:text-white px-3 py-2 transition">
                Voltar
            </button>
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left: items + totals --}}
        <div class="lg:col-span-2 space-y-5">

            {{-- Items --}}
            <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-zinc-800">
                    <h2 class="text-sm font-semibold text-white">Itens do pedido</h2>
                </div>
                <div class="divide-y divide-zinc-800/70">
                    @foreach($this->order->items as $item)
                    <div class="px-5 py-3.5">
                        <div class="flex items-start justify-between">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-white">
                                    {{ $item->displayName() }}
                                    <span class="text-zinc-500">×{{ $item->quantity }}</span>
                                </p>
                                @if($item->notes)
                                <p class="text-xs text-zinc-500 mt-0.5 italic">{{ $item->notes }}</p>
                                @endif
                                @foreach($item->addons as $addon)
                                <p class="text-xs text-zinc-500 mt-0.5">+ {{ $addon->option_name }}</p>
                                @endforeach
                            </div>
                            <span class="text-sm text-zinc-200 tabular-nums ml-4 shrink-0">
                                R$ {{ number_format($item->subtotal, 2, ',', '.') }}
                            </span>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="px-5 py-4 border-t border-zinc-800 space-y-2">
                    <div class="flex items-center justify-between text-sm text-zinc-400">
                        <span>Subtotal</span>
                        <span class="tabular-nums">R$ {{ number_format($this->order->subtotal, 2, ',', '.') }}</span>
                    </div>
                    @if($this->order->delivery_fee > 0)
                    <div class="flex items-center justify-between text-sm text-zinc-400">
                        <span>Taxa de entrega</span>
                        <span class="tabular-nums">R$ {{ number_format($this->order->delivery_fee, 2, ',', '.') }}</span>
                    </div>
                    @endif
                    @if($this->order->discount > 0)
                    <div class="flex items-center justify-between text-sm text-green-400">
                        <span>Desconto</span>
                        <span class="tabular-nums">- R$ {{ number_format($this->order->discount, 2, ',', '.') }}</span>
                    </div>
                    @endif
                    <div class="flex items-center justify-between text-base font-bold text-white border-t border-zinc-700 pt-2">
                        <span>Total</span>
                        <span class="tabular-nums">R$ {{ number_format($this->order->total, 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>

            {{-- Status history --}}
            @if($this->order->statusHistory->isNotEmpty())
            <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
                <div class="px-5 py-4 border-b border-zinc-800">
                    <h2 class="text-sm font-semibold text-white">Histórico de status</h2>
                </div>
                <div class="px-5 py-4 space-y-3">
                    @foreach($this->order->statusHistory as $history)
                    <div class="flex items-start gap-3">
                        <div class="size-2 rounded-full bg-orange-400 mt-1.5 shrink-0"></div>
                        <div>
                            <p class="text-sm text-white">
                                @if($history->from_status)
                                {{ $history->from_status->label() }} → {{ $history->to_status->label() }}
                                @else
                                {{ $history->to_status->label() }}
                                @endif
                            </p>
                            <p class="text-xs text-zinc-500">{{ $history->changed_at->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>

        {{-- Right: customer + payment + delivery --}}
        <div class="space-y-5">

            {{-- Customer --}}
            <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-5">
                <h2 class="text-sm font-semibold text-white mb-4">Cliente</h2>
                <div class="space-y-2.5 text-sm">
                    <div>
                        <p class="text-zinc-500 text-xs">Nome</p>
                        <p class="text-white">{{ $this->order->customer_name ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-zinc-500 text-xs">Telefone</p>
                        <p class="text-zinc-300">{{ $this->order->customer_phone ?: '—' }}</p>
                    </div>
                    @if($this->order->notes)
                    <div>
                        <p class="text-zinc-500 text-xs">Observações</p>
                        <p class="text-zinc-300 italic">{{ $this->order->notes }}</p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Delivery address --}}
            @if($this->order->delivery_type === DeliveryType::Delivery)
            <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-5">
                <h2 class="text-sm font-semibold text-white mb-4">Endereço de entrega</h2>
                <div class="text-sm text-zinc-300 space-y-0.5">
                    <p>{{ $this->order->delivery_address_street }}, {{ $this->order->delivery_address_number }}
                        @if($this->order->delivery_address_complement)
                        · {{ $this->order->delivery_address_complement }}
                        @endif
                    </p>
                    <p>{{ $this->order->delivery_address_neighborhood }}</p>
                    <p>{{ $this->order->delivery_address_city }} – {{ $this->order->delivery_address_state }}</p>
                    <p class="text-zinc-500">CEP {{ $this->order->delivery_address_zip }}</p>
                </div>
            </div>
            @endif

            {{-- Payment --}}
            @if($this->order->payment)
            <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-5">
                <h2 class="text-sm font-semibold text-white mb-4">Pagamento</h2>
                <div class="space-y-2.5 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-500">Método</span>
                        <span class="text-zinc-300">{{ $this->order->payment->method->label() }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-500">Status</span>
                        @if($this->order->payment->isPaid())
                        <span class="text-green-400 text-xs font-medium">Pago</span>
                        @else
                        <span class="text-yellow-400 text-xs font-medium">Pendente</span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-500">Valor</span>
                        <span class="text-zinc-200 tabular-nums">R$ {{ number_format($this->order->payment->amount, 2, ',', '.') }}</span>
                    </div>
                    @if($this->order->payment->paid_at)
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-500">Pago em</span>
                        <span class="text-zinc-400 text-xs">{{ $this->order->payment->paid_at->format('d/m/Y H:i') }}</span>
                    </div>
                    @endif
                </div>
            </div>
            @endif

        </div>
    </div>
</div>
