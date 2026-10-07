<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use App\Enums\DiningTableStatus;
use App\Enums\WaitlistStatus;
use App\Models\DiningTable;
use App\Models\Restaurant;
use App\Models\WaitlistEntry;

new #[Layout('components.layouts.app')] class extends Component {

    public string $partyName = '';
    public string $partySize = '';
    public string $phone     = '';

    /** Entry whose inline "seat at table" picker is open. */
    public ?int $seatingId = null;
    public string $tableId = '';

    private function rid(): ?int
    {
        return Restaurant::query()->value('id');
    }

    #[Computed]
    public function waiting()
    {
        return WaitlistEntry::where('restaurant_id', $this->rid())
            ->waiting()
            ->orderBy('created_at') // FIFO
            ->get();
    }

    #[Computed]
    public function freeTables()
    {
        return DiningTable::where('restaurant_id', $this->rid())
            ->where('status', DiningTableStatus::Free->value)
            ->orderBy('number')
            ->get();
    }

    #[Computed]
    public function seatedTodayCount(): int
    {
        return WaitlistEntry::where('restaurant_id', $this->rid())
            ->where('status', WaitlistStatus::Seated->value)
            ->whereDate('seated_at', today())
            ->count();
    }

    public function add(): void
    {
        $this->validate([
            'partyName' => 'required|string|max:100',
            'partySize' => 'required|integer|min:1|max:50',
            'phone'     => 'nullable|string|max:30',
        ]);

        WaitlistEntry::create([
            'restaurant_id' => $this->rid(),
            'party_name'    => $this->partyName,
            'party_size'    => (int) $this->partySize,
            'phone'         => $this->phone ?: null,
        ]);

        $this->reset('partyName', 'partySize', 'phone');
        unset($this->waiting);
    }

    public function openSeat(int $entryId): void
    {
        $this->findEntry($entryId); // fence before opening the picker

        $this->seatingId = $entryId;
        $this->tableId   = '';
    }

    public function cancelSeat(): void
    {
        $this->seatingId = null;
        $this->tableId   = '';
    }

    public function seat(int $entryId): void
    {
        $entry = $this->findEntry($entryId);
        if (! $entry->isWaiting()) {
            return; // stale button — another attendant already handled it
        }

        $table = $this->tableId
            ? DiningTable::where('restaurant_id', $this->rid())->findOrFail((int) $this->tableId)
            : null;

        $entry->update([
            'status'          => WaitlistStatus::Seated,
            'dining_table_id' => $table?->id,
            'seated_at'       => now(),
        ]);

        // Seating at a table occupies it (dining-room state).
        $table?->update(['status' => DiningTableStatus::Occupied]);

        $this->cancelSeat();
        unset($this->waiting, $this->freeTables, $this->seatedTodayCount);
    }

    public function remove(int $entryId): void
    {
        $entry = $this->findEntry($entryId);
        if (! $entry->isWaiting()) {
            return;
        }

        $entry->update([
            'status'     => WaitlistStatus::Removed,
            'removed_at' => now(),
        ]);

        $this->cancelSeat();
        unset($this->waiting);
    }

    /** Entry lookup fenced to this restaurant — a bare findOrFail would cross tenants. */
    private function findEntry(int $entryId): WaitlistEntry
    {
        return WaitlistEntry::where('restaurant_id', $this->rid())->findOrFail($entryId);
    }
}; ?>

<div wire:poll.30s>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Fila de espera</h1>
            <p class="text-sm text-zinc-400 mt-0.5">
                {{ $this->waiting->count() }} grupo(s) aguardando ·
                {{ $this->seatedTodayCount }} sentado(s) hoje
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.dining.tables') }}" wire:navigate
               class="text-sm text-zinc-300 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 rounded-lg px-3 py-1.5 transition">
                Ver mesas
            </a>
            <span class="inline-flex items-center gap-1.5 text-xs text-zinc-400 bg-zinc-800 border border-zinc-700 rounded-lg px-3 py-1.5">
                <span class="size-1.5 rounded-full bg-green-400 animate-pulse"></span>
                Atualiza a cada 30s
            </span>
        </div>
    </div>

    {{-- Add form: two required fields, always at hand for the hostess --}}
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-4 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-[1fr_120px_180px_auto] gap-2">
            <div>
                <input wire:model="partyName" wire:keydown.enter="add" type="text" placeholder="Nome do cliente *"
                       class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                @error('partyName') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <input wire:model="partySize" wire:keydown.enter="add" type="number" min="1" max="50" placeholder="Pessoas *"
                       class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                @error('partySize') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <input wire:model="phone" wire:keydown.enter="add" type="text" placeholder="Telefone (opcional)"
                       class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
            </div>
            <button wire:click="add" wire:loading.attr="disabled"
                    class="text-sm font-medium text-white bg-orange-500 hover:bg-orange-600 rounded-xl px-4 py-2.5 transition whitespace-nowrap">
                <span wire:loading.remove wire:target="add">+ Adicionar</span>
                <span wire:loading wire:target="add">Adicionando…</span>
            </button>
        </div>
    </div>

    {{-- Waiting list (FIFO) --}}
    @if($this->waiting->isEmpty())
    <div class="bg-zinc-900 border border-zinc-800 border-dashed rounded-xl px-5 py-14 text-center">
        <p class="text-zinc-400 text-sm font-medium">Ninguém aguardando.</p>
        <p class="text-zinc-600 text-xs mt-1">Adicione um grupo acima quando chegar gente sem mesa livre.</p>
    </div>
    @else
    <div class="space-y-3">
        @foreach($this->waiting as $entry)
        @php $minutes = (int) $entry->created_at->diffInMinutes(now()); @endphp
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-4" wire:key="wl-{{ $entry->id }}">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="flex items-center justify-center size-8 rounded-full bg-zinc-800 text-sm font-bold text-zinc-300 tabular-nums shrink-0">
                        {{ $loop->iteration }}
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-white truncate">{{ $entry->party_name }}</p>
                        <p class="text-xs text-zinc-500">
                            {{ $entry->party_size }} pessoa(s)
                            @if($entry->phone) · {{ $entry->phone }} @endif
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <span class="text-xs tabular-nums px-2 py-1 rounded-lg {{ $minutes >= 30 ? 'text-red-400 bg-red-400/10' : ($minutes >= 15 ? 'text-yellow-400 bg-yellow-400/10' : 'text-zinc-400 bg-zinc-800') }}">
                        {{ $minutes }} min
                    </span>

                    @if($seatingId !== $entry->id)
                    <button wire:click="openSeat({{ $entry->id }})"
                            class="text-xs font-medium text-white bg-teal-600 hover:bg-teal-500 rounded-lg px-3 py-2 transition">
                        Sentar
                    </button>
                    <button wire:click="remove({{ $entry->id }})"
                            wire:confirm="Remover '{{ $entry->party_name }}' da fila?"
                            class="text-xs text-red-400/70 hover:text-red-400 bg-zinc-800 hover:bg-red-400/10 rounded-lg px-3 py-2 transition">
                        Remover
                    </button>
                    @endif
                </div>
            </div>

            {{-- Inline seat picker --}}
            @if($seatingId === $entry->id)
            <div class="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t border-zinc-800">
                <select wire:model="tableId"
                        class="flex-1 min-w-40 bg-zinc-800 border border-zinc-700 text-sm text-zinc-300 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-teal-500">
                    <option value="">Sem mesa (só marcar como sentado)</option>
                    @foreach($this->freeTables as $table)
                    <option value="{{ $table->id }}">Mesa {{ $table->number }}{{ $table->capacity ? ' ('.$table->capacity.' lugares)' : '' }}</option>
                    @endforeach
                </select>
                <button wire:click="seat({{ $entry->id }})" wire:loading.attr="disabled"
                        class="text-xs font-medium text-white bg-teal-600 hover:bg-teal-500 rounded-xl px-4 py-2.5 transition">
                    Confirmar
                </button>
                <button wire:click="cancelSeat"
                        class="text-xs text-zinc-400 hover:text-white bg-zinc-800 hover:bg-zinc-700 rounded-xl px-4 py-2.5 transition">
                    Cancelar
                </button>
                @if($this->freeTables->isEmpty())
                <p class="w-full text-xs text-zinc-500">Nenhuma mesa livre agora — dá para sentar sem vincular mesa.</p>
                @endif
            </div>
            @endif
        </div>
        @endforeach
    </div>
    @endif
</div>
