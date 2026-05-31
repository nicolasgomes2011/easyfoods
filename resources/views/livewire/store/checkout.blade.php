<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use App\Actions\Orders\PlaceOrder;
use App\Enums\DeliveryType;
use App\Models\Cart;
use App\Models\DiningTable;
use App\Models\Restaurant;

new #[Layout('components.layouts.customer')] class extends Component {

    public string $deliveryType = 'delivery';

    // Customer info
    public string $customerName  = '';
    public string $customerPhone = '';
    public string $notes         = '';

    // Delivery address
    public string $street       = '';
    public string $addressNumber = '';
    public string $complement   = '';
    public string $neighborhood = '';
    public string $city         = '';
    public string $state        = '';
    public string $zip          = '';

    // Dine-in
    public string $tableId = '';

    // Payment
    public string $paymentMethod = 'cash';

    public ?string $error = null;

    public function mount(): void
    {
        $cart = $this->currentCart();
        if (! $cart || $cart->items()->count() === 0) {
            $this->redirect(route('store.cart'), navigate: true);
        }
    }

    #[Computed]
    public function restaurant(): ?Restaurant
    {
        return Restaurant::first();
    }

    #[Computed]
    public function cart(): ?Cart
    {
        if (! $this->restaurant) return null;
        return Cart::with(['items.product', 'items.variant'])
            ->where('session_id', session()->getId())
            ->where('restaurant_id', $this->restaurant->id)
            ->first();
    }

    #[Computed]
    public function tables()
    {
        return DiningTable::where('restaurant_id', $this->restaurant?->id)
            ->where('status', 'free')
            ->orderBy('number')
            ->get();
    }

    #[Computed]
    public function subtotal(): float
    {
        if (! $this->cart) return 0.0;
        return $this->cart->items->sum(fn ($item) => $item->unitPrice() * $item->quantity);
    }

    #[Computed]
    public function deliveryFee(): float
    {
        return $this->deliveryType === 'delivery' ? 5.00 : 0.0;
    }

    #[Computed]
    public function total(): float
    {
        return $this->subtotal + $this->deliveryFee;
    }

    #[Computed]
    public function acceptedPayments(): array
    {
        $restaurant = $this->restaurant;
        if (! $restaurant) return [];

        $restaurant->load('settings');
        $map = [
            'cash'        => 'payment_cash',
            'pix'         => 'payment_pix',
            'credit_card' => 'payment_credit_card',
            'debit_card'  => 'payment_debit_card',
            'meal_voucher'=> 'payment_meal_voucher',
        ];

        $labels = [
            'cash'        => 'Dinheiro',
            'pix'         => 'Pix',
            'credit_card' => 'Cartão de crédito',
            'debit_card'  => 'Cartão de débito',
            'meal_voucher'=> 'Vale-refeição',
        ];

        $active = [];
        foreach ($map as $key => $settingKey) {
            if ($restaurant->getSetting($settingKey, false)) {
                $active[$key] = $labels[$key];
            }
        }

        return $active ?: $labels;
    }

    public function rules(): array
    {
        $rules = [
            'customerName'  => 'required|string|max:100',
            'customerPhone' => 'nullable|string|max:20',
            'deliveryType'  => 'required|in:delivery,pickup,dine_in',
            'paymentMethod' => 'required|string',
            'notes'         => 'nullable|string|max:500',
        ];

        if ($this->deliveryType === 'delivery') {
            $rules['street']       = 'required|string|max:150';
            $rules['addressNumber']= 'required|string|max:20';
            $rules['city']         = 'required|string|max:100';
            $rules['state']        = 'required|string|max:2';
            $rules['zip']          = 'required|string|max:10';
        }

        if ($this->deliveryType === 'dine_in') {
            $rules['tableId'] = 'required|exists:dining_tables,id';
        }

        return $rules;
    }

    private function currentCart(): ?Cart
    {
        $restaurant = Restaurant::first();
        if (! $restaurant) return null;
        return Cart::where('session_id', session()->getId())
            ->where('restaurant_id', $restaurant->id)
            ->first();
    }

    public function placeOrder(): void
    {
        $this->error = null;
        $this->validate();

        $cart = $this->currentCart();
        if (! $cart) {
            $this->error = 'Carrinho não encontrado.';
            return;
        }

        $table = $this->deliveryType === 'dine_in'
            ? DiningTable::find($this->tableId)
            : null;

        try {
            $order = app(PlaceOrder::class)->handle($cart, [
                'delivery_type'  => $this->deliveryType === 'dine_in' ? 'dine_in' : $this->deliveryType,
                'customer_name'  => $this->customerName,
                'customer_phone' => $this->customerPhone ?: null,
                'notes'          => $this->notes ?: null,
                'delivery_fee'   => $this->deliveryFee,
                'dining_table_id'=> $table?->id,
                'table_number'   => $table?->number,
                // Address
                'street'         => $this->street ?: null,
                'address_number' => $this->addressNumber ?: null,
                'complement'     => $this->complement ?: null,
                'neighborhood'   => $this->neighborhood ?: null,
                'city'           => $this->city ?: null,
                'state'          => $this->state ?: null,
                'zip'            => $this->zip ?: null,
            ]);

            $this->redirect(route('store.order.tracking', $order->number), navigate: true);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
        }
    }
}; ?>

<div>
    <div class="mb-6">
        <a href="{{ route('store.cart') }}" wire:navigate class="text-sm text-zinc-400 hover:text-zinc-600 transition">← Voltar ao carrinho</a>
        <h1 class="text-2xl font-bold text-zinc-800 mt-2">Checkout</h1>
    </div>

    @if($error)
    <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-600 text-sm rounded-xl">
        {{ $error }}
    </div>
    @endif

    <form wire:submit="placeOrder" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-2 space-y-5">

            {{-- Tipo de entrega --}}
            <div class="bg-white border border-zinc-200 rounded-2xl p-5">
                <h2 class="text-sm font-semibold text-zinc-700 mb-3">Como quer receber?</h2>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    @foreach([
                        ['value' => 'delivery', 'label' => 'Delivery',     'show' => $this->restaurant?->accepts_delivery],
                        ['value' => 'pickup',   'label' => 'Retirada',     'show' => $this->restaurant?->accepts_pickup],
                        ['value' => 'dine_in',  'label' => 'Mesa (salão)', 'show' => $this->restaurant?->accepts_dine_in],
                    ] as $type)
                    @if($type['show'])
                    <label class="relative cursor-pointer">
                        <input type="radio" wire:model.live="deliveryType" value="{{ $type['value'] }}" class="sr-only peer" />
                        <div class="border-2 rounded-xl px-3 py-2.5 text-sm text-center transition peer-checked:border-orange-500 peer-checked:text-orange-600 peer-checked:bg-orange-50 border-zinc-200 text-zinc-600 hover:border-zinc-300">
                            {{ $type['label'] }}
                        </div>
                    </label>
                    @endif
                    @endforeach
                </div>
            </div>

            {{-- Endereço (delivery only) --}}
            @if($deliveryType === 'delivery')
            <div class="bg-white border border-zinc-200 rounded-2xl p-5 space-y-3">
                <h2 class="text-sm font-semibold text-zinc-700">Endereço de entrega</h2>
                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <input wire:model="street" type="text" placeholder="Logradouro *"
                               class="w-full bg-zinc-50 border border-zinc-200 text-sm rounded-xl px-3 py-2.5 text-zinc-800 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-orange-400" />
                        @error('street') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <input wire:model="addressNumber" type="text" placeholder="Número *"
                               class="w-full bg-zinc-50 border border-zinc-200 text-sm rounded-xl px-3 py-2.5 text-zinc-800 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-orange-400" />
                        @error('addressNumber') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <input wire:model="complement" type="text" placeholder="Complemento"
                           class="w-full bg-zinc-50 border border-zinc-200 text-sm rounded-xl px-3 py-2.5 text-zinc-800 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-orange-400" />
                    <input wire:model="neighborhood" type="text" placeholder="Bairro"
                           class="w-full bg-zinc-50 border border-zinc-200 text-sm rounded-xl px-3 py-2.5 text-zinc-800 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-orange-400" />
                </div>
                <div class="grid grid-cols-5 gap-3">
                    <div class="col-span-3">
                        <input wire:model="city" type="text" placeholder="Cidade *"
                               class="w-full bg-zinc-50 border border-zinc-200 text-sm rounded-xl px-3 py-2.5 text-zinc-800 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-orange-400" />
                        @error('city') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <input wire:model="state" type="text" placeholder="UF *" maxlength="2"
                               class="w-full bg-zinc-50 border border-zinc-200 text-sm rounded-xl px-3 py-2.5 text-zinc-800 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-orange-400 uppercase" />
                        @error('state') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <input wire:model="zip" type="text" placeholder="CEP *"
                               class="w-full bg-zinc-50 border border-zinc-200 text-sm rounded-xl px-3 py-2.5 text-zinc-800 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-orange-400" />
                        @error('zip') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
            @endif

            {{-- Mesa (dine-in only) --}}
            @if($deliveryType === 'dine_in')
            <div class="bg-white border border-zinc-200 rounded-2xl p-5">
                <h2 class="text-sm font-semibold text-zinc-700 mb-3">Selecione sua mesa</h2>
                @if($this->tables->isEmpty())
                <p class="text-sm text-zinc-500">Nenhuma mesa livre no momento.</p>
                @else
                <div class="grid grid-cols-4 sm:grid-cols-6 gap-2">
                    @foreach($this->tables as $table)
                    <label class="cursor-pointer">
                        <input type="radio" wire:model="tableId" value="{{ $table->id }}" class="sr-only peer" />
                        <div class="border-2 rounded-xl py-2.5 text-sm text-center transition peer-checked:border-orange-500 peer-checked:bg-orange-50 peer-checked:text-orange-600 border-zinc-200 text-zinc-600 hover:border-zinc-300">
                            {{ $table->number }}
                        </div>
                    </label>
                    @endforeach
                </div>
                @error('tableId') <p class="text-red-500 text-xs mt-2">{{ $message }}</p> @enderror
                @endif
            </div>
            @endif

            {{-- Dados do cliente --}}
            <div class="bg-white border border-zinc-200 rounded-2xl p-5 space-y-3">
                <h2 class="text-sm font-semibold text-zinc-700">Seus dados</h2>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <input wire:model="customerName" type="text" placeholder="Nome *"
                               class="w-full bg-zinc-50 border border-zinc-200 text-sm rounded-xl px-3 py-2.5 text-zinc-800 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-orange-400" />
                        @error('customerName') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <input wire:model="customerPhone" type="text" placeholder="Telefone"
                               class="w-full bg-zinc-50 border border-zinc-200 text-sm rounded-xl px-3 py-2.5 text-zinc-800 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-orange-400" />
                    </div>
                </div>
                <textarea wire:model="notes" rows="2" placeholder="Observações para o pedido (opcional)"
                          class="w-full bg-zinc-50 border border-zinc-200 text-sm rounded-xl px-3 py-2.5 text-zinc-800 placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-orange-400 resize-none"></textarea>
            </div>

            {{-- Pagamento --}}
            <div class="bg-white border border-zinc-200 rounded-2xl p-5">
                <h2 class="text-sm font-semibold text-zinc-700 mb-3">Forma de pagamento</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    @foreach($this->acceptedPayments as $key => $label)
                    <label class="cursor-pointer">
                        <input type="radio" wire:model="paymentMethod" value="{{ $key }}" class="sr-only peer" />
                        <div class="border-2 rounded-xl px-2.5 py-2 text-sm text-center transition peer-checked:border-orange-500 peer-checked:bg-orange-50 peer-checked:text-orange-600 border-zinc-200 text-zinc-600">
                            {{ $label }}
                        </div>
                    </label>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Order summary sidebar --}}
        <div class="lg:col-span-1">
            <div class="bg-white border border-zinc-200 rounded-2xl p-5 sticky top-24">
                <h2 class="text-sm font-semibold text-zinc-700 mb-4">Resumo do pedido</h2>

                <div class="space-y-2 mb-4">
                    @if($this->cart)
                    @foreach($this->cart->items as $item)
                    <div class="flex justify-between text-sm text-zinc-600">
                        <span class="flex-1 truncate">{{ $item->quantity }}× {{ $item->product?->name }}</span>
                        <span class="ml-2 tabular-nums">R$ {{ number_format($item->unitPrice() * $item->quantity, 2, ',', '.') }}</span>
                    </div>
                    @endforeach
                    @endif
                </div>

                <div class="border-t border-zinc-100 pt-3 space-y-1.5 text-sm">
                    <div class="flex justify-between text-zinc-500">
                        <span>Subtotal</span>
                        <span class="tabular-nums">R$ {{ number_format($this->subtotal, 2, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-zinc-500">
                        <span>Taxa de entrega</span>
                        <span class="tabular-nums">{{ $this->deliveryFee > 0 ? 'R$ ' . number_format($this->deliveryFee, 2, ',', '.') : 'Grátis' }}</span>
                    </div>
                    <div class="flex justify-between font-bold text-zinc-800 pt-2 border-t border-zinc-100 text-base">
                        <span>Total</span>
                        <span class="tabular-nums">R$ {{ number_format($this->total, 2, ',', '.') }}</span>
                    </div>
                </div>

                <button type="submit" wire:loading.attr="disabled"
                        class="w-full mt-5 py-3.5 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-2xl transition text-sm">
                    <span wire:loading.remove wire:target="placeOrder">Confirmar pedido</span>
                    <span wire:loading wire:target="placeOrder">Processando…</span>
                </button>
            </div>
        </div>

    </form>
</div>
