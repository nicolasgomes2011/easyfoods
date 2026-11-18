<?php
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;
use App\Actions\Dining\AddTableSessionGuest;
use App\Actions\Dining\AssignOrderItemToGuest;
use App\Actions\Dining\RenameTableSessionGuest;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Exceptions\TabNotEditable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Support\CurrentRestaurant;

/*
 * Comanda da mesa (Salão › Mesas): every order of a table session with its
 * items, the consolidated total, what was already paid and what is pending.
 * All amounts come from the server: the total is the sum of the orders' totals
 * (recomputed on the server when items change) and leaves canceled orders out;
 * paid is the sum of payments with status "paid" of those orders.
 * People at the table: while the session is open, staff adds and renames
 * people and assigns each item to one of them; the tab shows the subtotal of
 * each person's items and of the items without a person (shared). Assigning
 * never changes the item snapshot nor the order totals.
 * Dining room staff only (admin, manager, attendant), checked again on every
 * action; another restaurant's session is a 404.
 */
new #[Layout('components.layouts.app')] class extends Component {
    #[Locked]
    public int $sessionId;

    public string $newGuestName = '';

    public ?int $editingGuestId = null;

    public string $editingGuestName = '';

    public function mount(TableSession $tableSession): void
    {
        $this->authorizeStaff();
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

    #[Computed]
    public function guests()
    {
        return TableSessionGuest::where('restaurant_id', CurrentRestaurant::id())
            ->where('table_session_id', $this->sessionId)
            ->orderBy('id')
            ->get();
    }

    /** @return array{total: float, paid: float, pending: float, guests: array<int, float>, unassigned: float} */
    #[Computed]
    public function summary(): array
    {
        $counted = $this->orders->reject(fn (Order $order) => $order->status === OrderStatus::Canceled);

        $total = round((float) $counted->sum(fn (Order $order) => (float) $order->total), 2);
        $paid  = round((float) $counted->sum(fn (Order $order) => $order->payments
            ->filter(fn ($payment) => $payment->status === PaymentStatus::Paid)
            ->sum(fn ($payment) => (float) $payment->amount)), 2);

        // Item subtotals already include the add-ons (snapshot on the line).
        $items = $counted->flatMap(fn (Order $order) => $order->items);
        $lineTotal = fn ($lines) => round((float) $lines->sum(fn (OrderItem $item) => (float) $item->subtotal), 2);

        return [
            'total'      => $total,
            'paid'       => $paid,
            'pending'    => max(0.0, round($total - $paid, 2)),
            'guests'     => $items->whereNotNull('table_session_guest_id')
                ->groupBy('table_session_guest_id')
                ->map($lineTotal)
                ->all(),
            'unassigned' => $lineTotal($items->whereNull('table_session_guest_id')),
        ];
    }

    public function addGuest(AddTableSessionGuest $action): void
    {
        $this->authorizeStaff();
        $this->resetErrorBag('newGuestName');

        try {
            $action->execute($this->tableSession, $this->newGuestName);
        } catch (TabNotEditable $e) {
            $this->addError('newGuestName', $e->getMessage());

            return;
        }

        $this->reset('newGuestName');
        unset($this->guests);
    }

    public function startRename(int $guestId): void
    {
        $this->authorizeStaff();
        $this->resetErrorBag('editingGuestName');

        $guest = $this->findGuest($guestId);
        $this->editingGuestId = $guest->id;
        $this->editingGuestName = $guest->name;
    }

    public function cancelRename(): void
    {
        $this->reset('editingGuestId', 'editingGuestName');
        $this->resetErrorBag('editingGuestName');
    }

    public function saveRename(RenameTableSessionGuest $action): void
    {
        $this->authorizeStaff();

        if ($this->editingGuestId === null) {
            return;
        }

        try {
            $action->execute($this->findGuest($this->editingGuestId), $this->editingGuestName);
        } catch (TabNotEditable $e) {
            $this->addError('editingGuestName', $e->getMessage());

            return;
        }

        $this->cancelRename();
        unset($this->guests);
    }

    /** $guestId '' (or null) = no person: the item goes back to shared. */
    public function assignItem(int $itemId, int|string|null $guestId): void
    {
        $this->authorizeStaff();
        $this->resetErrorBag('assign');

        $item = $this->findItem($itemId);
        $guest = ($guestId === null || $guestId === '') ? null : $this->findGuest((int) $guestId);

        try {
            app(AssignOrderItemToGuest::class)->execute($item, $guest);
        } catch (TabNotEditable $e) {
            $this->addError('assign', $e->getMessage());

            return;
        }

        unset($this->orders, $this->summary);
    }

    /** Livewire actions do not go through the route middleware again. */
    private function authorizeStaff(): void
    {
        abort_unless(
            auth()->user()?->hasAnyRole(UserRole::Admin, UserRole::Manager, UserRole::Attendant) ?? false,
            403
        );
    }

    /** A person of this session (and restaurant) only; anything else is a 404. */
    private function findGuest(int $guestId): TableSessionGuest
    {
        return TableSessionGuest::where('restaurant_id', CurrentRestaurant::id())
            ->where('table_session_id', $this->sessionId)
            ->whereKey($guestId)
            ->first() ?? abort(404);
    }

    /** An item of an order of this session (and restaurant) only; anything else is a 404. */
    private function findItem(int $itemId): OrderItem
    {
        return OrderItem::whereKey($itemId)
            ->whereHas('order', fn ($query) => $query
                ->where('restaurant_id', CurrentRestaurant::id())
                ->where('table_session_id', $this->sessionId))
            ->first() ?? abort(404);
    }
}; ?>

@php
    $money = fn ($value) => 'R$ '.number_format((float) $value, 2, ',', '.');
    $session = $this->tableSession;
    $summary = $this->summary;
    $guests = $this->guests;
    $guestNames = $guests->pluck('name', 'id');
    $open = $session->isOpen();
@endphp

<div class="max-w-4xl">
    <div class="flex items-start justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Comanda da mesa</h1>
            <p class="text-sm text-zinc-400 mt-0.5">
                Mesa {{ $session->diningTable?->number }}
                @if($session->opened_at) · aberta às {{ $session->opened_at->format('H:i') }} @endif
                @unless($open) · conta fechada @endunless
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

    {{-- People at the table: subtotal of each person's items; items without a person are shared. --}}
    <section data-tab-guests class="bg-zinc-900 border border-zinc-800 rounded-xl p-4 mb-6">
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
            <h2 class="text-sm font-semibold text-white">Pessoas na mesa</h2>
            <p class="text-xs text-zinc-500">Subtotal dos itens de cada pessoa</p>
        </div>

        @if($guests->isEmpty())
        <p class="text-sm text-zinc-500 mb-3">Nenhuma pessoa ainda. Adicione as pessoas para separar os itens de cada uma.</p>
        @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 mb-3">
            @foreach($guests as $guest)
            <div data-guest="{{ $guest->id }}" wire:key="tab-guest-{{ $guest->id }}"
                 class="flex items-center justify-between gap-2 rounded-lg bg-zinc-800/60 px-3 py-2 min-h-12">
                @if($open && $editingGuestId === $guest->id)
                <form wire:submit="saveRename" class="flex flex-1 items-center gap-2 min-w-0">
                    <input type="text" wire:model="editingGuestName" maxlength="{{ TableSessionGuest::NAME_MAX }}" aria-label="Nome da pessoa"
                           class="min-w-0 flex-1 rounded-md bg-zinc-900 border border-zinc-700 px-2 py-1.5 text-sm text-white focus:border-orange-500 focus:outline-none">
                    <button type="submit" class="text-xs font-medium text-orange-400 hover:text-orange-300 px-1 py-1.5">Salvar</button>
                    <button type="button" wire:click="cancelRename" class="text-xs text-zinc-400 hover:text-white px-1 py-1.5">Cancelar</button>
                </form>
                @else
                <div class="min-w-0">
                    <p class="text-sm text-white truncate">{{ $guest->name }}</p>
                    <p class="text-xs text-zinc-400 tabular-nums"><span data-guest-subtotal="{{ $guest->id }}">{{ $money($summary['guests'][$guest->id] ?? 0) }}</span></p>
                </div>
                @if($open)
                <button type="button" wire:click="startRename({{ $guest->id }})" class="shrink-0 text-xs text-zinc-400 hover:text-white px-1 py-1.5">Renomear</button>
                @endif
                @endif
            </div>
            @endforeach
            <div class="rounded-lg border border-dashed border-zinc-700 px-3 py-2 min-h-12">
                <p class="text-sm text-zinc-300">Sem pessoa</p>
                <p class="text-xs text-zinc-500 tabular-nums"><span data-unassigned-subtotal>{{ $money($summary['unassigned']) }}</span></p>
            </div>
        </div>
        @error('editingGuestName') <p class="text-xs text-red-400 mb-2">{{ $message }}</p> @enderror
        @endif

        @if($open)
        <form wire:submit="addGuest" class="flex flex-wrap items-center gap-2">
            <input type="text" wire:model="newGuestName" maxlength="{{ TableSessionGuest::NAME_MAX }}" placeholder="Nome (opcional)" aria-label="Nome da nova pessoa"
                   class="min-w-0 flex-1 sm:flex-none sm:w-56 rounded-md bg-zinc-800 border border-zinc-700 px-3 py-2 text-sm text-white placeholder-zinc-500 focus:border-orange-500 focus:outline-none">
            <button type="submit" class="rounded-md bg-orange-500 px-3 py-2 text-sm font-medium text-white hover:bg-orange-600">Adicionar pessoa</button>
        </form>
        @endif
        @error('newGuestName') <p class="text-xs text-red-400 mt-2">{{ $message }}</p> @enderror
    </section>

    @error('assign')
    <div class="mb-3 rounded-lg border border-red-900 bg-red-950/40 px-3 py-2 text-sm text-red-300">{{ $message }}</div>
    @enderror

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
                <li class="flex justify-between gap-3" wire:key="tab-item-{{ $item->id }}">
                    <span class="min-w-0">
                        {{ $item->quantity }}× {{ $item->displayName() }}
                        @if($item->addons->isNotEmpty())
                        <span class="block text-xs text-zinc-500">+ {{ $item->addons->pluck('addon_option_name')->join(', ') }}</span>
                        @endif
                        @if($open && ! $canceled && $guests->isNotEmpty())
                        <select data-item-guest="{{ $item->id }}" aria-label="Pessoa do item"
                                wire:change="assignItem({{ $item->id }}, $event.target.value)"
                                class="mt-1 block rounded-md bg-zinc-800 border border-zinc-700 px-2 py-1.5 text-xs text-zinc-200 focus:border-orange-500 focus:outline-none">
                            <option value="" @selected($item->table_session_guest_id === null)>Sem pessoa</option>
                            @foreach($guests as $guest)
                            <option value="{{ $guest->id }}" @selected($item->table_session_guest_id === $guest->id)>{{ $guest->name }}</option>
                            @endforeach
                        </select>
                        @elseif($item->table_session_guest_id !== null && $guestNames->has($item->table_session_guest_id))
                        <span class="block text-xs text-zinc-500">{{ $guestNames->get($item->table_session_guest_id) }}</span>
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
