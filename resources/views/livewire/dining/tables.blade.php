<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use App\Enums\DiningTableStatus;
use App\Models\DiningTable;
use App\Models\Restaurant;

new #[Layout('components.layouts.app')] class extends Component {

    public bool   $showForm    = false;
    public string $number      = '';
    public int    $capacity    = 4;
    public string $editingId   = '';

    public function rules(): array
    {
        return [
            'number'   => 'required|string|max:20',
            'capacity' => 'required|integer|min:1|max:30',
        ];
    }

    #[Computed]
    public function tables()
    {
        return DiningTable::where('restaurant_id', Restaurant::query()->value('id'))
            ->orderBy('number')->get();
    }

    #[Computed]
    public function counts(): array
    {
        return [
            'total'    => $this->tables->count(),
            'free'     => $this->tables->where('status', DiningTableStatus::Free)->count(),
            'occupied' => $this->tables->where('status', DiningTableStatus::Occupied)->count(),
            'reserved' => $this->tables->where('status', DiningTableStatus::Reserved)->count(),
        ];
    }

    public function openCreate(): void
    {
        $this->reset('number', 'capacity', 'editingId');
        $this->capacity  = 4;
        $this->showForm  = true;
    }

    public function openEdit(DiningTable $table): void
    {
        $this->number    = $table->number;
        $this->capacity  = $table->capacity;
        $this->editingId = (string) $table->id;
        $this->showForm  = true;
    }

    public function save(): void
    {
        $this->validate();

        if ($this->editingId) {
            DiningTable::findOrFail($this->editingId)->update([
                'number'   => $this->number,
                'capacity' => $this->capacity,
            ]);
        } else {
            DiningTable::create([
                'restaurant_id' => Restaurant::query()->value('id'),
                'number'        => $this->number,
                'capacity'      => $this->capacity,
                'status'        => DiningTableStatus::Free,
            ]);
        }

        $this->showForm = false;
        $this->reset('number', 'capacity', 'editingId');
        unset($this->tables, $this->counts);
    }

    public function setStatus(DiningTable $table, string $status): void
    {
        $table->update(['status' => $status]);
        unset($this->tables, $this->counts);
    }

    public function delete(DiningTable $table): void
    {
        $table->delete();
        unset($this->tables, $this->counts);
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->reset('number', 'capacity', 'editingId');
    }
}; ?>

<div>
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Salão — Mesas</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Controle de ocupação em tempo real</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.dining.queue') }}" wire:navigate
               class="text-sm text-zinc-300 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 rounded-lg px-3 py-1.5 transition">
                Fila de espera
            </a>
            <button wire:click="openCreate"
                    class="text-sm text-white bg-orange-500 hover:bg-orange-600 rounded-lg px-3 py-1.5 transition">
                + Nova mesa
            </button>
        </div>
    </div>

    {{-- Modal form --}}
    @if($showForm)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" wire:click.self="cancelForm">
        <div class="bg-zinc-900 border border-zinc-700 rounded-2xl w-full max-w-sm mx-4 p-6 shadow-xl">
            <h2 class="text-base font-semibold text-white mb-5">
                {{ $editingId ? 'Editar mesa' : 'Nova mesa' }}
            </h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">Número / identificação</label>
                    <input
                        wire:model="number"
                        type="text"
                        placeholder="Ex: 1, 2, A1, Varanda…"
                        class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500"
                    />
                    @error('number') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">Capacidade (pessoas)</label>
                    <input
                        wire:model="capacity"
                        type="number"
                        min="1"
                        max="30"
                        class="w-full bg-zinc-800 border border-zinc-700 text-white text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500"
                    />
                    @error('capacity') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex gap-2 mt-6">
                <button wire:click="cancelForm"
                        class="flex-1 text-sm text-zinc-300 bg-zinc-800 hover:bg-zinc-700 border border-zinc-700 rounded-xl px-4 py-2.5 transition">
                    Cancelar
                </button>
                <button wire:click="save" wire:loading.attr="disabled"
                        class="flex-1 text-sm text-white bg-orange-500 hover:bg-orange-600 rounded-xl px-4 py-2.5 transition font-medium">
                    <span wire:loading.remove wire:target="save">{{ $editingId ? 'Salvar' : 'Adicionar' }}</span>
                    <span wire:loading wire:target="save">Salvando…</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Summary --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        @php $c = $this->counts; @endphp
        @foreach([
            ['label' => 'Total de mesas', 'value' => $c['total'],    'color' => 'text-white'],
            ['label' => 'Ocupadas',        'value' => $c['occupied'], 'color' => 'text-orange-400'],
            ['label' => 'Livres',          'value' => $c['free'],     'color' => 'text-green-400'],
            ['label' => 'Reservadas',      'value' => $c['reserved'], 'color' => 'text-blue-400'],
        ] as $stat)
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-4 py-3 text-center">
            <p class="text-2xl font-bold tabular-nums {{ $stat['color'] }}">{{ $stat['value'] }}</p>
            <p class="text-xs text-zinc-400 mt-1">{{ $stat['label'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Table list --}}
    @if($this->tables->isEmpty())
    <div class="bg-zinc-900 border border-zinc-800 border-dashed rounded-xl px-5 py-16 text-center">
        <p class="text-zinc-500 text-sm mb-3">Nenhuma mesa cadastrada ainda.</p>
        <button wire:click="openCreate"
                class="text-sm text-orange-400 hover:text-orange-300 transition">
            + Adicionar primeira mesa
        </button>
    </div>
    @else
    @php
    $statusBg = [
        'green'  => 'bg-green-400/10 text-green-400 border-green-400/20',
        'orange' => 'bg-orange-400/10 text-orange-400 border-orange-400/20',
        'blue'   => 'bg-blue-400/10 text-blue-400 border-blue-400/20',
    ];
    @endphp
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-zinc-800">
                    <th class="text-left px-5 py-3 text-xs font-medium text-zinc-500">Mesa</th>
                    <th class="text-center px-4 py-3 text-xs font-medium text-zinc-500">Capacidade</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-zinc-500">Status</th>
                    <th class="text-right px-5 py-3 text-xs font-medium text-zinc-500">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800/70">
                @foreach($this->tables as $table)
                @php $color = $table->status->color(); @endphp
                <tr class="hover:bg-zinc-800/30 transition">
                    <td class="px-5 py-3.5">
                        <span class="font-semibold text-white">Mesa {{ $table->number }}</span>
                    </td>
                    <td class="px-4 py-3.5 text-center text-zinc-400">
                        {{ $table->capacity }} lug.
                    </td>
                    <td class="px-4 py-3.5">
                        <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $statusBg[$color] ?? '' }}">
                            {{ $table->status->label() }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center justify-end gap-1">
                            {{-- Status toggle --}}
                            @if($table->status !== \App\Enums\DiningTableStatus::Free)
                            <button wire:click="setStatus({{ $table->id }}, 'free')"
                                    class="text-xs text-green-400 bg-green-400/10 hover:bg-green-400/20 rounded-lg px-2.5 py-1.5 transition">
                                Liberar
                            </button>
                            @endif
                            @if($table->status !== \App\Enums\DiningTableStatus::Occupied)
                            <button wire:click="setStatus({{ $table->id }}, 'occupied')"
                                    class="text-xs text-orange-400 bg-orange-400/10 hover:bg-orange-400/20 rounded-lg px-2.5 py-1.5 transition">
                                Ocupar
                            </button>
                            @endif
                            @if($table->status !== \App\Enums\DiningTableStatus::Reserved)
                            <button wire:click="setStatus({{ $table->id }}, 'reserved')"
                                    class="text-xs text-blue-400 bg-blue-400/10 hover:bg-blue-400/20 rounded-lg px-2.5 py-1.5 transition">
                                Reservar
                            </button>
                            @endif
                            <button wire:click="openEdit({{ $table->id }})"
                                    class="text-xs text-zinc-400 hover:text-white bg-zinc-800 hover:bg-zinc-700 rounded-lg px-2.5 py-1.5 transition">
                                Editar
                            </button>
                            <button wire:click="delete({{ $table->id }})"
                                    wire:confirm="Excluir a mesa {{ $table->number }}?"
                                    class="text-xs text-red-400/70 hover:text-red-400 bg-zinc-800 hover:bg-red-400/10 rounded-lg px-2.5 py-1.5 transition">
                                Excluir
                            </button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
