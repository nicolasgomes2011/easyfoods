<?php
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\Restaurant;
use Illuminate\Support\Str;

new #[Layout('components.layouts.app')] class extends Component {

    public string $name         = '';
    public string $description  = '';
    public string $phone        = '';
    public string $email        = '';
    public string $street       = '';
    public string $number       = '';
    public string $complement   = '';
    public string $neighborhood = '';
    public string $city         = '';
    public string $state        = '';
    public string $zip          = '';
    public bool   $acceptsDelivery = true;
    public bool   $acceptsPickup   = true;
    public int    $minOrderMinutes = 20;
    public int    $maxOrderMinutes = 45;

    public bool $saved = false;

    public function mount(): void
    {
        $restaurant = Restaurant::first();

        if ($restaurant) {
            $this->name         = $restaurant->name;
            $this->description  = $restaurant->description ?? '';
            $this->phone        = $restaurant->phone ?? '';
            $this->email        = $restaurant->email ?? '';
            $this->street       = $restaurant->address_street ?? '';
            $this->number       = $restaurant->address_number ?? '';
            $this->complement   = $restaurant->address_complement ?? '';
            $this->neighborhood = $restaurant->address_neighborhood ?? '';
            $this->city         = $restaurant->address_city ?? '';
            $this->state        = $restaurant->address_state ?? '';
            $this->zip          = $restaurant->address_zip ?? '';
            $this->acceptsDelivery  = $restaurant->accepts_delivery;
            $this->acceptsPickup    = $restaurant->accepts_pickup;
            $this->minOrderMinutes  = $restaurant->min_order_minutes ?? 20;
            $this->maxOrderMinutes  = $restaurant->max_order_minutes ?? 45;
        }
    }

    public function rules(): array
    {
        return [
            'name'            => 'required|string|max:100',
            'description'     => 'nullable|string|max:500',
            'phone'           => 'nullable|string|max:20',
            'email'           => 'nullable|email|max:100',
            'street'          => 'nullable|string|max:150',
            'number'          => 'nullable|string|max:20',
            'complement'      => 'nullable|string|max:100',
            'neighborhood'    => 'nullable|string|max:100',
            'city'            => 'nullable|string|max:100',
            'state'           => 'nullable|string|max:2',
            'zip'             => 'nullable|string|max:10',
            'acceptsDelivery' => 'boolean',
            'acceptsPickup'   => 'boolean',
            'minOrderMinutes' => 'required|integer|min:1|max:240',
            'maxOrderMinutes' => 'required|integer|min:1|max:240',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name'                  => $this->name,
            'slug'                  => Str::slug($this->name),
            'description'           => $this->description ?: null,
            'phone'                 => $this->phone ?: null,
            'email'                 => $this->email ?: null,
            'address_street'        => $this->street ?: null,
            'address_number'        => $this->number ?: null,
            'address_complement'    => $this->complement ?: null,
            'address_neighborhood'  => $this->neighborhood ?: null,
            'address_city'          => $this->city ?: null,
            'address_state'         => $this->state ?: null,
            'address_zip'           => $this->zip ?: null,
            'accepts_delivery'      => $this->acceptsDelivery,
            'accepts_pickup'        => $this->acceptsPickup,
            'min_order_minutes'     => $this->minOrderMinutes,
            'max_order_minutes'     => $this->maxOrderMinutes,
            'is_active'             => true,
        ];

        $restaurant = Restaurant::first();

        if ($restaurant) {
            $restaurant->update($data);
        } else {
            Restaurant::create($data);
        }

        $this->saved = true;
    }

    public function updatedName(): void { $this->saved = false; }
}; ?>

<div>
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-white">Configurações da Loja</h1>
            <p class="text-sm text-zinc-400 mt-0.5">Informações do restaurante visíveis pelos clientes</p>
        </div>
        @if($saved)
        <span class="text-sm text-green-400 bg-green-400/10 border border-green-400/20 rounded-lg px-3 py-1.5">
            Salvo
        </span>
        @endif
    </div>

    <form wire:submit="save" class="space-y-6">
        {{-- Informações básicas --}}
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-5 space-y-4">
            <h2 class="text-sm font-semibold text-zinc-300 border-b border-zinc-800 pb-3">Informações básicas</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">Nome do restaurante *</label>
                    <input wire:model="name" type="text" placeholder="Nome do seu restaurante"
                           class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                    @error('name') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">Telefone</label>
                    <input wire:model="phone" type="text" placeholder="(11) 99999-9999"
                           class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                    @error('phone') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-zinc-400 mb-1.5">E-mail de contato</label>
                <input wire:model="email" type="email" placeholder="contato@restaurante.com"
                       class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                @error('email') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-zinc-400 mb-1.5">Descrição</label>
                <textarea wire:model="description" rows="2" placeholder="Uma breve descrição do restaurante"
                          class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500 resize-none"></textarea>
                @error('description') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        {{-- Endereço --}}
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-5 space-y-4">
            <h2 class="text-sm font-semibold text-zinc-300 border-b border-zinc-800 pb-3">Endereço</h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">Logradouro</label>
                    <input wire:model="street" type="text" placeholder="Rua, Avenida…"
                           class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">Número</label>
                    <input wire:model="number" type="text" placeholder="123"
                           class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">Complemento</label>
                    <input wire:model="complement" type="text" placeholder="Apto, Sala…"
                           class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">Bairro</label>
                    <input wire:model="neighborhood" type="text" placeholder="Centro"
                           class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">Cidade</label>
                    <input wire:model="city" type="text" placeholder="São Paulo"
                           class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">UF</label>
                    <input wire:model="state" type="text" placeholder="SP" maxlength="2"
                           class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500 uppercase" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">CEP</label>
                    <input wire:model="zip" type="text" placeholder="00000-000"
                           class="w-full bg-zinc-800 border border-zinc-700 text-white placeholder-zinc-500 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                </div>
            </div>
        </div>

        {{-- Operações --}}
        <div class="bg-zinc-900 border border-zinc-800 rounded-xl p-5 space-y-4">
            <h2 class="text-sm font-semibold text-zinc-300 border-b border-zinc-800 pb-3">Operações</h2>

            <div class="space-y-3">
                <label class="flex items-center gap-3 cursor-pointer select-none">
                    <input type="checkbox" wire:model="acceptsDelivery"
                           class="rounded border-zinc-600 bg-zinc-800 text-orange-500 focus:ring-orange-500" />
                    <div>
                        <span class="text-sm text-zinc-200">Aceita delivery</span>
                        <p class="text-xs text-zinc-500">Permite que clientes solicitem entrega em domicílio</p>
                    </div>
                </label>

                <label class="flex items-center gap-3 cursor-pointer select-none">
                    <input type="checkbox" wire:model="acceptsPickup"
                           class="rounded border-zinc-600 bg-zinc-800 text-orange-500 focus:ring-orange-500" />
                    <div>
                        <span class="text-sm text-zinc-200">Aceita retirada no balcão</span>
                        <p class="text-xs text-zinc-500">Permite que clientes retirem o pedido no local</p>
                    </div>
                </label>
            </div>

            <div class="grid grid-cols-2 gap-4 pt-2">
                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">Tempo mínimo de preparo (min)</label>
                    <input wire:model="minOrderMinutes" type="number" min="1" max="240"
                           class="w-full bg-zinc-800 border border-zinc-700 text-white text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                    @error('minOrderMinutes') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-400 mb-1.5">Tempo máximo de preparo (min)</label>
                    <input wire:model="maxOrderMinutes" type="number" min="1" max="240"
                           class="w-full bg-zinc-800 border border-zinc-700 text-white text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-500" />
                    @error('maxOrderMinutes') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" wire:loading.attr="disabled"
                    class="text-sm text-white bg-orange-500 hover:bg-orange-600 rounded-xl px-5 py-2.5 font-medium transition">
                <span wire:loading.remove wire:target="save">Salvar configurações</span>
                <span wire:loading wire:target="save">Salvando…</span>
            </button>
        </div>
    </form>
</div>
