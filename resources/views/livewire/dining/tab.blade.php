<?php
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\TableSession;
use App\Support\CurrentRestaurant;

/*
 * Comanda da mesa (Salão › Mesas): every order of a table session with its
 * items, the consolidated total, what was already paid and what is pending.
 * All amounts come from the server: the total is the sum of the orders' totals
 * (recomputed on the server when items change) and leaves canceled orders out;
 * paid is the sum of payments with status "paid" of those orders.
 * Dining room staff only (admin, manager, attendant); another restaurant's
 * session is a 404.
 */
new #[Layout('components.layouts.app')] class extends Component {
    #[Locked]
    public int $sessionId;

    public function mount(TableSession $tableSession): void
    {
        abort_unless(
            auth()->user()?->hasAnyRole(UserRole::Admin, UserRole::Manager, UserRole::Attendant) ?? false,
            403
        );
        abort_unless($tableSession->restaurant_id === CurrentRestaurant::id(), 404);

        $this->sessionId = $tableSession->id;
    }

    #[Computed]
    public function tableSession(): TableSession
    {
        return TableSession::where('restaurant_id', CurrentRestaurant::id())
            ->with('diningTable')
            ->findOrFail($this->sessionId);
    }

    #[Computed]
    public function orders()
    {
        return Order::where('restaurant_id', CurrentRestaurant::id())
            ->where('table_session_id', $this->sessionId)
            ->with(['items.addons', 'payments'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /** @return array{total: float, paid: float, pending: float} */
    #[Computed]
    public function summary(): array
    {
        $counted = $this->orders->reject(fn (Order $order) => $order->status === OrderStatus::Canceled);

        $total = round((float) $counted->sum(fn (Order $order) => (float) $order->total), 2);
        $paid  = round((float) $counted->sum(fn (Order $order) => $order->payments
            ->filter(fn ($payment) => $payment->status === PaymentStatus::Paid)
            ->sum(fn ($payment) => (float) $payment->amount)), 2);

        return ['total' => $total, 'paid' => $paid, 'pending' => max(0.0, round($total - $paid, 2))];
    }
}; ?>

@php
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $session = $this->tableSession;
    $summary = $this->summary;
@endphp

<div class="max-w-4xl">
    <div class="flex items-start justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Comanda da mesa</h1>
            <p class="text-sm text-zinc-400 mt-0.5">
                Mesa {{ $session->diningTable?->number }}
                @if($session->opened_at) · aberta às {{ $session->opened_at->format('H:i') }} @endif
                @unless($session->isOpen()) · conta fechada @endunless
            </p>
        </div>
        <a href="{{ route('admin.dining.tables') }}" wire:navigate class="text-sm text-zinc-400 hover:text-white whitespace-nowrap">← Mesas</a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3">
            <p class="text-xs text-zinc-400">Total da mesa</p>
            <p class="text-2xl font-bold text-white tabular-nums mt-1"><span data-tab-total>{{ $money($summary['total']) }}</span></p>
        </div>
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3">
            <p class="text-xs text-zinc-400">Pago</p>
            <p class="text-2xl font-bold text-green-400 tabular-nums mt-1"><span data-tab-paid>{{ $money($summary['paid']) }}</span></p>
        </div>
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3">
            <p class="text-xs text-zinc-400">Pendente</p>
            <p class="text-2xl font-bold tabular-nums mt-1 {{ $summary['pending'] > 0 ? 'text-orange-400' : 'text-zinc-300' }}"><span data-tab-pending>{{ $money($summary['pending']) }}</span></p>
        </div>
    </div>

    @if($this->orders->isEmpty())
    <div class="bg-zinc-900 border border-zinc-800 border-dashed rounded-xl px-5 py-10 text-center text-sm text-zinc-500">
        Nenhum pedido nesta mesa ainda.
    </div>
    @else
    <div class="space-y-3">
        @foreach($this->orders as $order)
        @php $canceled = $order->status === OrderStatus::Canceled; @endphp
        <section data-tab-order="{{ $order->id }}" wire:key="tab-order-{{ $order->id }}"
                 class="bg-zinc-900 border border-zinc-800 rounded-xl p-4 {{ $canceled ? 'opacity-60' : '' }}">
            <div class="flex items-center justify-between gap-3 mb-2">
                <div class="flex flex-wrap items-center gap-2 min-w-0">
                    <a href="{{ route('admin.orders.show', $order) }}" wire:navigate class="font-semibold text-white hover:text-orange-300">Pedido #{{ $order->number }}</a>
                    <span class="text-xs text-zinc-500">{{ $order->created_at?->format('H:i') }}</span>
                    <span class="rounded-full bg-zinc-800 px-2 py-0.5 text-xs text-zinc-300">{{ $order->status->label() }}</span>
                </div>
                <span class="text-sm font-semibold tabular-nums whitespace-nowrap {{ $canceled ? 'text-zinc-500 line-through' : 'text-white' }}">{{ $money($order->total) }}</span>
            </div>
            <ul class="space-y-1 text-sm text-zinc-300">
                @foreach($order->items as $item)
                <li class="flex justify-between gap-3">
                    <span class="min-w-0">
                        {{ $item->quantity }}× {{ $item->displayName() }}
                        @if($item->addons->isNotEmpty())
                        <span class="block text-xs text-zinc-500">+ {{ $item->addons->pluck('addon_option_name')->join(', ') }}</span>
                        @endif
                    </span>
                    <span class="tabular-nums text-zinc-400 whitespace-nowrap">{{ $money($item->subtotal) }}</span>
                </li>
                @endforeach
            </ul>
        </section>
        @endforeach
    </div>
    @endif
</div>
