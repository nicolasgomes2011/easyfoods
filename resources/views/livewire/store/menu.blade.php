<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use App\Enums\DeliveryType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;

new #[Layout('components.layouts.customer')] class extends Component {

    #[Url]
    public string $category = '';

    public ?int $showProductId = null;
    public int $qty = 1;
    public string $notes = '';

    public ?string $flashMessage = null;

    #[Computed]
    public function restaurant(): ?Restaurant
    {
        return Restaurant::first();
    }

    #[Computed]
    public function categories()
    {
        if (! $this->restaurant) return collect();
        return Category::where('restaurant_id', $this->restaurant->id)
            ->active()->ordered()
            ->withCount(['products' => fn ($q) => $q->where('availability_status', 'available')])
            ->having('products_count', '>', 0)
            ->get();
    }

    #[Computed]
    public function products()
    {
        if (! $this->restaurant) return collect();

        $query = Product::where('restaurant_id', $this->restaurant->id)
            ->available()
            ->with(['category', 'variants'])
            ->ordered();

        if ($this->category) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $this->category));
        }

        return $query->get();
    }

    #[Computed]
    public function cartItemCount(): int
    {
        $cart = $this->currentCart();
        return $cart ? $cart->items()->sum('quantity') : 0;
    }

    private function currentCart(): ?Cart
    {
        return Cart::where('session_id', session()->getId())
            ->where('restaurant_id', $this->restaurant?->id)
            ->first();
    }

    public function openProduct(int $productId): void
    {
        $this->showProductId = $productId;
        $this->qty   = 1;
        $this->notes = '';
    }

    public function closeProduct(): void
    {
        $this->showProductId = null;
        $this->reset('qty', 'notes');
    }

    public function addToCart(int $productId): void
    {
        $restaurant = $this->restaurant;
        if (! $restaurant) return;

        $product = Product::findOrFail($productId);

        $cart = Cart::firstOrCreate(
            ['session_id' => session()->getId(), 'restaurant_id' => $restaurant->id],
            ['delivery_type' => DeliveryType::Delivery->value]
        );

        $existing = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $productId)
            ->whereNull('product_variant_id')
            ->first();

        if ($existing) {
            $existing->increment('quantity', $this->qty);
        } else {
            CartItem::create([
                'cart_id'    => $cart->id,
                'product_id' => $productId,
                'quantity'   => $this->qty,
                'notes'      => $this->notes ?: null,
            ]);
        }

        $this->closeProduct();
        $this->flashMessage = "'{$product->name}' adicionado ao carrinho.";
        unset($this->cartItemCount);
    }
}; ?>

<div>
    @if($this->restaurant)

    {{-- Flash --}}
    @if($flashMessage)
    <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 3000)" x-show="show" x-transition
         class="fixed top-4 right-4 z-50 bg-green-500 text-white text-sm px-4 py-2.5 rounded-xl shadow-lg">
        {{ $flashMessage }}
    </div>
    @endif

    {{-- Restaurant header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-zinc-800">{{ $this->restaurant->name }}</h1>
        @if($this->restaurant->description)
        <p class="text-sm text-zinc-500 mt-1">{{ $this->restaurant->description }}</p>
        @endif

        <div class="flex flex-wrap gap-3 mt-3 text-xs text-zinc-500">
            @if($this->restaurant->accepts_delivery)
            <span class="flex items-center gap-1">
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" /></svg>
                Delivery
            </span>
            @endif
            @if($this->restaurant->accepts_pickup)
            <span class="flex items-center gap-1">
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" /></svg>
                Retirada
            </span>
            @endif
            <span>{{ $this->restaurant->min_order_minutes }}–{{ $this->restaurant->max_order_minutes }} min</span>
        </div>
    </div>

    {{-- Category tabs --}}
    @if($this->categories->isNotEmpty())
    <div class="flex gap-2 mb-5 overflow-x-auto pb-1 scrollbar-hide">
        <button wire:click="$set('category', '')"
                class="shrink-0 rounded-full px-3.5 py-1.5 text-sm font-medium transition {{ !$category ? 'bg-orange-500 text-white' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200' }}">
            Tudo
        </button>
        @foreach($this->categories as $cat)
        <button wire:click="$set('category', '{{ $cat->slug }}')"
                class="shrink-0 rounded-full px-3.5 py-1.5 text-sm font-medium transition {{ $category === $cat->slug ? 'bg-orange-500 text-white' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200' }}">
            {{ $cat->name }}
        </button>
        @endforeach
    </div>
    @endif

    {{-- Product grid --}}
    @if($this->products->isEmpty())
    <div class="py-16 text-center text-zinc-400 text-sm">
        Nenhum produto disponível no momento.
    </div>
    @else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($this->products as $product)
        <div class="bg-white border border-zinc-200 rounded-2xl overflow-hidden hover:shadow-md transition group">
            {{-- Image placeholder --}}
            <div class="h-36 bg-zinc-100 flex items-center justify-center">
                @if($product->image)
                <img src="{{ $product->image }}" alt="{{ $product->name }}" class="w-full h-full object-cover" />
                @else
                <svg class="size-12 text-zinc-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8.25v-1.5m0 1.5c-1.355 0-2.697.056-4.024.166C6.845 8.51 6 9.473 6 10.608v2.513m6-4.871c1.355 0 2.697.056 4.024.166C17.155 8.51 18 9.473 18 10.608v2.513M15 8.25v-1.5m-6 1.5v-1.5m12 9.75-1.5.75a3.354 3.354 0 0 1-3 0 3.354 3.354 0 0 1-3 0 3.354 3.354 0 0 1-3 0 3.354 3.354 0 0 1-3 0l-1.5-.75M8.25 19.5l1.5-7.5L12 13.5l2.25-1.5 1.5 7.5" /></svg>
                @endif
            </div>

            <div class="p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-zinc-800 truncate">{{ $product->name }}</h3>
                        @if($product->description)
                        <p class="text-xs text-zinc-500 mt-0.5 line-clamp-2">{{ $product->description }}</p>
                        @endif
                    </div>
                    @if($product->is_featured)
                    <span class="shrink-0 text-[10px] font-semibold text-orange-500 bg-orange-50 rounded-full px-1.5 py-0.5">destaque</span>
                    @endif
                </div>

                <div class="flex items-center justify-between mt-3">
                    <span class="font-bold text-zinc-800">R$ {{ number_format($product->price, 2, ',', '.') }}</span>
                    <button wire:click="openProduct({{ $product->id }})"
                            class="flex items-center gap-1 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-xl px-3 py-1.5 transition">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        Adicionar
                    </button>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Cart FAB --}}
    @if($this->cartItemCount > 0)
    <a href="{{ route('store.cart') }}" wire:navigate
       class="fixed bottom-6 right-6 z-40 flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white rounded-2xl px-5 py-3.5 shadow-lg transition">
        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" /></svg>
        <span class="font-semibold">Ver carrinho</span>
        <span class="bg-white text-orange-500 text-xs font-bold rounded-full px-1.5 py-0.5 min-w-[1.25rem] text-center">
            {{ $this->cartItemCount }}
        </span>
    </a>
    @endif

    {{-- Product quick-add modal --}}
    @if($showProductId)
    @php $p = \App\Models\Product::find($showProductId) @endphp
    @if($p)
    <div class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50" wire:click.self="closeProduct">
        <div class="bg-white rounded-t-3xl sm:rounded-2xl w-full max-w-md p-6 shadow-xl">
            <h3 class="text-lg font-bold text-zinc-800">{{ $p->name }}</h3>
            @if($p->description)
            <p class="text-sm text-zinc-500 mt-1">{{ $p->description }}</p>
            @endif
            <p class="text-xl font-bold text-zinc-800 mt-3">R$ {{ number_format($p->price, 2, ',', '.') }}</p>

            <div class="mt-4 space-y-3">
                <div>
                    <label class="block text-xs font-medium text-zinc-500 mb-1.5">Observações</label>
                    <input wire:model="notes" type="text" placeholder="Ex: sem cebola, bem passado…"
                           class="w-full bg-zinc-50 border border-zinc-200 text-zinc-800 placeholder-zinc-400 text-sm rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-orange-400" />
                </div>

                <div class="flex items-center justify-between">
                    <label class="text-xs font-medium text-zinc-500">Quantidade</label>
                    <div class="flex items-center gap-3">
                        <button wire:click="$set('qty', {{ max(1, $qty - 1) }})"
                                class="size-8 flex items-center justify-center rounded-full border border-zinc-300 text-zinc-600 hover:bg-zinc-100 transition">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" /></svg>
                        </button>
                        <span class="text-sm font-semibold text-zinc-800 w-4 text-center">{{ $qty }}</span>
                        <button wire:click="$set('qty', {{ $qty + 1 }})"
                                class="size-8 flex items-center justify-center rounded-full border border-zinc-300 text-zinc-600 hover:bg-zinc-100 transition">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <button wire:click="closeProduct"
                        class="flex-1 py-3 rounded-xl border border-zinc-200 text-sm text-zinc-600 hover:bg-zinc-50 transition">
                    Cancelar
                </button>
                <button wire:click="addToCart({{ $p->id }})" wire:loading.attr="disabled"
                        class="flex-1 py-3 rounded-xl bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold transition">
                    <span wire:loading.remove wire:target="addToCart">Adicionar • R$ {{ number_format($p->price * $qty, 2, ',', '.') }}</span>
                    <span wire:loading wire:target="addToCart">Adicionando…</span>
                </button>
            </div>
        </div>
    </div>
    @endif
    @endif

    @else
    <div class="py-20 text-center text-zinc-400">
        <p>Loja não encontrada.</p>
    </div>
    @endif
</div>
