<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use App\Actions\Waste\RecordWaste;
use App\Enums\UserRole;
use App\Enums\WasteReason;
use App\Exceptions\WasteNotRecordable;
use App\Livewire\Concerns\CorrectsWasteEntries;
use App\Models\Ingredient;
use App\Models\WasteEntry;
use App\Support\CurrentRestaurant;
use Illuminate\Validation\Rule;

/*
 * Waste log (desperdício): the kitchen records an ingredient thrown away in a
 * few taps (ingredient → quantity → reason). Entries are append-only: no
 * delete, a correction is a new adjustment entry. Costs (snapshotted on each
 * entry) are shown to admin and manager only. Every lookup is fenced by
 * restaurant_id: another restaurant's ingredient or entry is a 404.
 */
new #[Layout('components.layouts.app')] class extends Component {
    use CorrectsWasteEntries;

    public string $search       = '';
    public string $ingredientId = '';
    public string $quantity     = '';
    public string $reason       = '';
    public string $note         = '';

    public ?string $statusMessage = null;

    public function mount(): void
    {
        $this->authorizeRole();
    }

    /** Livewire actions do not go through the route middleware again: every write checks the role. */
    private function authorizeRole(): void
    {
        abort_unless(
            auth()->user()?->hasAnyRole(UserRole::Admin, UserRole::Manager, UserRole::Kitchen) ?? false,
            403
        );
    }

    private function rid(): ?int
    {
        return CurrentRestaurant::id();
    }

    /** Scoped lookup: another restaurant's ingredient is a 404. Archived ones are refused by RecordWaste. */
    private function scopedIngredient(int $ingredientId): Ingredient
    {
        return Ingredient::where('restaurant_id', $this->rid())->findOrFail($ingredientId);
    }

    private function scopedEntry(int $entryId): WasteEntry
    {
        return WasteEntry::where('restaurant_id', $this->rid())->findOrFail($entryId);
    }

    #[Computed]
    public function canSeeCosts(): bool
    {
        return auth()->user()?->hasAnyRole(UserRole::Admin, UserRole::Manager) ?? false;
    }

    #[Computed]
    public function hasIngredients(): bool
    {
        return Ingredient::where('restaurant_id', $this->rid())->whereNull('archived_at')->exists();
    }

    #[Computed]
    public function ingredients()
    {
        $search = trim($this->search);

        return Ingredient::where('restaurant_id', $this->rid())
            ->whereNull('archived_at')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function selectedIngredient(): ?Ingredient
    {
        if ($this->ingredientId === '') {
            return null;
        }

        return Ingredient::where('restaurant_id', $this->rid())->find((int) $this->ingredientId);
    }

    #[Computed]
    public function todayEntries()
    {
        return WasteEntry::where('restaurant_id', $this->rid())
            ->where('created_at', '>=', today())
            ->with(['recordedBy:id,name', 'adjusts:id,created_at'])
            ->withSum('adjustments', 'quantity')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    #[Computed]
    public function todayCost(): float
    {
        return round($this->todayEntries->sum(fn (WasteEntry $entry) => $entry->totalCost()), 2);
    }

    /** After a new entry or a correction: the list and the total are read again. */
    private function refreshEntries(): void
    {
        unset($this->todayEntries, $this->todayCost);
    }

    public function selectIngredient(int $ingredientId): void
    {
        $this->ingredientId = (string) $this->scopedIngredient($ingredientId)->id;
        $this->resetErrorBag('ingredientId');
        unset($this->selectedIngredient);
    }

    public function selectReason(string $reason): void
    {
        $this->reason = WasteReason::tryFrom($reason)?->value ?? '';
        $this->resetErrorBag('reason');
    }

    public function record(RecordWaste $action): void
    {
        $this->authorizeRole();
        $this->statusMessage = null;

        $this->validate(
            [
                'ingredientId' => ['required', 'integer'],
                'quantity'     => ['required', 'numeric', 'gt:0', 'max:'.RecordWaste::MAX_QUANTITY],
                'reason'       => ['required', Rule::enum(WasteReason::class)],
                'note'         => ['nullable', 'string', 'max:255'],
            ],
            [
                'ingredientId.required' => 'Escolha o insumo descartado.',
                'reason.required'       => 'Escolha o motivo do descarte.',
            ],
            [
                'ingredientId' => 'insumo',
                'quantity'     => 'quantidade',
                'reason'       => 'motivo',
                'note'         => 'observação',
            ],
        );

        $ingredient = $this->scopedIngredient((int) $this->ingredientId);

        try {
            $entry = $action->execute(
                $ingredient,
                $this->quantity,
                WasteReason::from($this->reason),
                $this->note,
                auth()->user(),
            );
        } catch (WasteNotRecordable $e) {
            $this->addError($ingredient->isArchived() ? 'ingredientId' : 'quantity', $e->getMessage());

            return;
        }

        $this->statusMessage = "Descarte registrado: {$entry->formattedQuantity()} de {$entry->ingredient_name}.";
        $this->reset('search', 'ingredientId', 'quantity', 'reason', 'note');
        unset($this->ingredients, $this->selectedIngredient);
        $this->refreshEntries();
    }
}; ?>

<div class="max-w-5xl">
    <div class="flex items-start justify-between gap-3 mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Desperdício</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Registre o que foi jogado fora: insumo, quantidade e motivo.</p>
        </div>
        @if($this->canSeeCosts)
        <a href="{{ route('admin.reports.waste') }}" wire:navigate class="text-sm text-orange-400 hover:text-orange-300 whitespace-nowrap">Relatório de perdas →</a>
        @endif
    </div>

    @if($statusMessage)
    <div data-waste-status class="mb-4 rounded-xl border border-green-500/30 bg-green-500/10 px-4 py-3 text-sm text-green-300">
        {{ $statusMessage }}
    </div>
    @endif

    {{-- Record a discard: ingredient → quantity → reason --}}
    <section class="bg-zinc-900 border border-zinc-800 rounded-xl p-4 sm:p-5 mb-6">
        <h2 class="text-sm font-semibold text-white mb-4">Registrar descarte</h2>

        <div class="mb-5">
            <div class="flex items-center justify-between gap-3 mb-2">
                <p class="text-xs font-medium text-zinc-400">1. Insumo</p>
                @if($this->hasIngredients)
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Buscar insumo…"
                       class="w-40 sm:w-56 bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                @endif
            </div>

            @if(! $this->hasIngredients)
            <div class="rounded-xl border border-dashed border-zinc-700 px-4 py-6 text-center text-sm text-zinc-500">
                Nenhum insumo cadastrado.
                @if(auth()->user()?->role?->canManageCatalog())
                <a href="{{ route('admin.catalog.ingredients') }}" wire:navigate class="text-orange-400 hover:text-orange-300">Cadastrar insumos</a>
                @else
                Peça ao gerente para cadastrar os insumos.
                @endif
            </div>
            @elseif($this->ingredients->isEmpty())
            <p class="text-sm text-zinc-500 py-3">Nenhum insumo encontrado para "{{ $search }}".</p>
            @else
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-2">
                @foreach($this->ingredients as $ingredient)
                @php $isSelected = (string) $ingredient->id === $ingredientId; @endphp
                <button type="button"
                        wire:key="waste-ingredient-{{ $ingredient->id }}"
                        wire:click="selectIngredient({{ $ingredient->id }})"
                        class="min-h-14 rounded-xl border px-3 py-2 text-left transition {{ $isSelected ? 'border-orange-500 bg-orange-500/15' : 'border-zinc-700 bg-zinc-800 hover:border-zinc-500' }}">
                    <span class="block text-sm font-medium text-white">{{ $ingredient->name }}</span>
                    <span class="block text-xs text-zinc-400">{{ $ingredient->unit->symbol() }}</span>
                </button>
                @endforeach
            </div>
            @endif

            @if($this->selectedIngredient)
            <p class="text-xs text-zinc-400 mt-2">Selecionado: <span class="font-medium text-white">{{ $this->selectedIngredient->name }}</span></p>
            @endif
            @error('ingredientId') <p class="text-red-400 text-xs mt-2">{{ $message }}</p> @enderror
        </div>

        <div class="mb-5">
            <label for="waste-quantity" class="block text-xs font-medium text-zinc-400 mb-2">2. Quantidade</label>
            <div class="flex items-center gap-2 max-w-xs">
                <input id="waste-quantity" wire:model="quantity" type="number" inputmode="decimal" step="0.001" min="0" placeholder="0"
                       class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-lg rounded-xl px-3 py-3 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                @if($this->selectedIngredient)
                <span class="text-sm text-zinc-400">{{ $this->selectedIngredient->unit->symbol() }}</span>
                @endif
            </div>
            @error('quantity') <p class="text-red-400 text-xs mt-2">{{ $message }}</p> @enderror
        </div>

        <div class="mb-5">
            <p class="text-xs font-medium text-zinc-400 mb-2">3. Motivo</p>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                @foreach(WasteReason::cases() as $option)
                <button type="button"
                        wire:key="waste-reason-{{ $option->value }}"
                        wire:click="selectReason('{{ $option->value }}')"
                        class="min-h-12 rounded-xl border px-3 py-2 text-sm font-medium transition {{ $reason === $option->value ? 'border-orange-500 bg-orange-500/15 text-white' : 'border-zinc-700 bg-zinc-800 text-zinc-300 hover:border-zinc-500' }}">
                    {{ $option->label() }}
                </button>
                @endforeach
            </div>
            @error('reason') <p class="text-red-400 text-xs mt-2">{{ $message }}</p> @enderror
        </div>

        <div class="mb-5">
            <label for="waste-note" class="block text-xs font-medium text-zinc-400 mb-2">Observação (opcional)</label>
            <input id="waste-note" wire:model="note" type="text" maxlength="255" placeholder="Ex: caixa amassada na entrega"
                   class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
            @error('note') <p class="text-red-400 text-xs mt-2">{{ $message }}</p> @enderror
        </div>

        <button wire:click="record" wire:loading.attr="disabled"
                class="w-full sm:w-auto min-h-12 text-base font-semibold text-white bg-orange-500 hover:bg-orange-600 rounded-xl px-6 py-3 transition">
            <span wire:loading.remove wire:target="record">Registrar descarte</span>
            <span wire:loading wire:target="record">Registrando…</span>
        </button>
    </section>

    {{-- Today's entries (corrections included) --}}
    <section>
        <div class="flex items-center justify-between gap-3 mb-3">
            <h2 class="text-sm font-semibold text-white">Lançamentos de hoje</h2>
            @if($this->canSeeCosts && $this->todayEntries->isNotEmpty())
            <span class="text-sm text-zinc-300 tabular-nums">Total de hoje: {{ WasteEntry::formatMoney($this->todayCost) }}</span>
            @endif
        </div>

        @if($this->todayEntries->isEmpty())
        <div class="bg-zinc-900 border border-zinc-800 border-dashed rounded-xl px-5 py-10 text-center text-sm text-zinc-500">
            Nenhum descarte registrado hoje.
        </div>
        @else
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl divide-y divide-zinc-800/70">
            @foreach($this->todayEntries as $entry)
            <x-waste.entry-row :entry="$entry" :can-see-costs="$this->canSeeCosts" wire:key="waste-entry-{{ $entry->id }}" />
            @endforeach
        </div>
        @endif
    </section>

    {{-- Correction: a new adjustment entry, the original stays as it is --}}
    @if($this->correcting)
    <x-waste.correction-modal :entry="$this->correcting" />
    @endif
</div>
