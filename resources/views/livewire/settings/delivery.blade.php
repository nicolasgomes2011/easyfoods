<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use App\Models\Restaurant;
use App\Models\DeliveryZone;

new #[Layout('components.layouts.app')] class extends Component {

    public bool   $showForm        = false;
    public string $editingId       = '';
    public string $name            = '';
    public string $neighborhood    = '';
    public string $city            = '';
    public string $fee             = '';
    public string $estimatedMinutes = '';
    public bool   $isActive        = true;

    public function rules(): array
    {
        return [
            'name'             => 'required|string|max:100',
            'neighborhood'     => 'nullable|string|max:100',
            'city'             => 'required|string|max:100',
            'fee'              => 'required|numeric|min:0',
            'estimatedMinutes' => 'required|integer|min:1|max:300',
            'isActive'         => 'boolean',
        ];
    }

    #[Computed]
    public function zones()
    {
        $restaurant = Restaurant::first();
        if (! $restaurant) return collect();
        return $restaurant->deliveryZones()->orderBy('name')->get();
    }

    public function openCreate(): void
    {
        $this->reset('name', 'neighborhood', 'city', 'fee', 'estimatedMinutes', 'editingId');
        $this->isActive = true;
        $this->estimatedMinutes = '30';
        $this->showForm = true;
    }

    public function openEdit(DeliveryZone $zone): void
    {
        $this->editingId        = (string) $zone->id;
        $this->name             = $zone->name;
        $this->neighborhood     = $zone->neighborhood ?? '';
        $this->city             = $zone->city;
        $this->fee              = (string) $zone->fee;
        $this->estimatedMinutes = (string) $zone->estimated_minutes;
        $this->isActive         = $zone->is_active;
        $this->showForm         = true;
    }

    public function save(): void
    {
        $this->validate();

        $restaurant = Restaurant::first();
        if (! $restaurant) return;

        $data = [
            'restaurant_id'     => $restaurant->id,
            'name'              => $this->name,
            'neighborhood'      => $this->neighborhood ?: null,
            'city'              => $this->city,
            'fee'               => $this->fee,
            'estimated_minutes' => $this->estimatedMinutes,
            'is_active'         => $this->isActive,
        ];

        if ($this->editingId) {
            DeliveryZone::findOrFail($this->editingId)->update($data);
        } else {
            DeliveryZone::create($data);
        }

        $this->showForm = false;
        $this->reset('name', 'neighborhood', 'city', 'fee', 'estimatedMinutes', 'editingId');
        $this->isActive = true;
        unset($this->zones);
    }

    public function toggleActive(DeliveryZone $zone): void
    {
        $zone->update(['is_active' => ! $zone->is_active]);
        unset($this->zones);
    }

    public function delete(DeliveryZone $zone): void
    {
        $zone->delete();
        unset($this->zones);
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->reset('name', 'neighborhood', 'city', 'fee', 'estimatedMinutes', 'editingId');
        $this->isActive = true;
    }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Zonas de Entrega</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Bairros e cidades atendidos, com taxas e tempo estimado</p>
        </div>
        @if(\App\Models\Restaurant::exists())
        <button wire:click="openCreate"
                class="text-sm text-white bg-orange-500 hover:bg-orange-600 rounded-lg px-3 py-1.5 transition">
            + Nova zona
        </button>
        @endif
    </div>

    @if(! \App\Models\Restaurant::exists())
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl px-5 py-10 text-center">
        <p class="text-zinc-400 text-sm">Configure as informações do restaurante antes de definir as zonas de entrega.</p>
        <a href="{{ route('admin.settings.store') }}" wire:navigate
           class="inline-block mt-3 text-sm text-orange-400 hover:text-orange-300 transition">
            Ir para Configurações da Loja →
        </a>
    </div>
    @else

    {{-- Modal form --}}
    @if($showForm)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" wire:click.self="cancelForm">
        <div class="bg-zinc-900 border border-zinc-700 rounded-2xl w-full max-w-md mx-4 p-6 shadow-xl">
            <h2 class="text-base font-semibold text-white mb-5">
                {{ $editingId ? 'Editar zona' : 'Nova zona de entrega' }}
            </h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">Nome da zona *</label>
                    <input wire:model="name" type="text" placeholder="Ex: Zona Sul, Centro Expandido…"
                           class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                    @error('name') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-zinc-400 mb-1.5">Bairro(s)</label>
                        <input wire:model="neighborhood" type="text" placeholder="Opcional"
                               class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-zinc-400 mb-1.5">Cidade *</label>
                        <input wire:model="city" type="text" placeholder="São Paulo"
                               class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                        @error('city') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-zinc-400 mb-1.5">Taxa de entrega (R$) *</label>
                        <input wire:model="fee" type="number" step="0.01" min="0" placeholder="0.00"
                               class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                        @error('fee') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-zinc-400 mb-1.5">Tempo estimado (min) *</label>
                        <input wire:model="estimatedMinutes" type="number" min="1" placeholder="30"
                               class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                        @error('estimatedMinutes') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <label class="flex items-center gap-2.5 cursor-pointer select-none">
                    <input type="checkbox" wire:model="isActive"
                           class="rounded border-zinc-600 bg-zinc-800 text-orange-500 focus:ring-orange-500" />
                    <span class="text-sm text-zinc-300">Zona ativa</span>
                </label>
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

    {{-- List --}}
    @if($this->zones->isEmpty())
    <div class="bg-zinc-900 border border-zinc-800 border-dashed rounded-xl px-5 py-16 text-center">
        <p class="text-zinc-500 text-sm mb-3">Nenhuma zona de entrega cadastrada.</p>
        <button wire:click="openCreate"
                class="text-sm text-orange-400 hover:text-orange-300 transition">
            + Adicionar primeira zona
        </button>
    </div>
    @else
    <div class="bg-zinc-900 border border-zinc-800 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-zinc-800">
                    <th class="text-left px-5 py-3 text-xs font-medium text-zinc-500">Zona</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-zinc-500">Cidade</th>
                    <th class="text-right px-4 py-3 text-xs font-medium text-zinc-500">Taxa</th>
                    <th class="text-right px-4 py-3 text-xs font-medium text-zinc-500">Tempo</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-zinc-500">Status</th>
                    <th class="text-right px-5 py-3 text-xs font-medium text-zinc-500">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800/70">
                @foreach($this->zones as $zone)
                <tr class="hover:bg-zinc-800/30 transition">
                    <td class="px-5 py-3.5">
                        <p class="font-medium text-white">{{ $zone->name }}</p>
                        @if($zone->neighborhood)
                        <p class="text-xs text-zinc-500 mt-0.5">{{ $zone->neighborhood }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3.5 text-zinc-400">{{ $zone->city }}</td>
                    <td class="px-4 py-3.5 text-right text-zinc-300 tabular-nums">
                        {{ $zone->fee == 0 ? 'Grátis' : 'R$ ' . number_format($zone->fee, 2, ',', '.') }}
                    </td>
                    <td class="px-4 py-3.5 text-right text-zinc-400 tabular-nums">{{ $zone->estimated_minutes }} min</td>
                    <td class="px-4 py-3.5">
                        @if($zone->is_active)
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-green-400/10 text-green-400">Ativa</span>
                        @else
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium bg-zinc-700 text-zinc-400">Inativa</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center justify-end gap-1">
                            <button wire:click="toggleActive({{ $zone->id }})"
                                    class="text-xs text-zinc-400 hover:text-white bg-zinc-800 hover:bg-zinc-700 rounded-lg px-2.5 py-1.5 transition">
                                {{ $zone->is_active ? 'Desativar' : 'Ativar' }}
                            </button>
                            <button wire:click="openEdit({{ $zone->id }})"
                                    class="text-xs text-zinc-400 hover:text-white bg-zinc-800 hover:bg-zinc-700 rounded-lg px-2.5 py-1.5 transition">
                                Editar
                            </button>
                            <button wire:click="delete({{ $zone->id }})"
                                    wire:confirm="Excluir a zona '{{ $zone->name }}'?"
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
    @endif
</div>
