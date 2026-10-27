<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use App\Actions\Cart\AddItemToCart;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use App\Support\CurrentRestaurant;

new #[Layout('components.layouts.customer')] class extends Component {

    #[Url]
    public string $category = '';

    public ?int $showProductId = null;
    public int $qty = 1;
    public string $notes = '';

    public ?int $selectedVariant = null;
    /** @var array<int,mixed> groupId => optionId (single) | array of optionIds (multi) */
    public array $selectedAddons = [];

    public ?string $flashMessage = null;
    public ?string $modalError = null;

    #[Computed]
    public function restaurant(): ?Restaurant
    {
        return CurrentRestaurant::get();
    }

    #[Computed]
    public function categories()
    {
        if (! $this->restaurant) return collect();
        return Category::where('restaurant_id', $this->restaurant->id)
            ->active()->ordered()
            ->whereHas('products', fn ($q) => $q->where('availability_status', 'available')->whereNull('archived_at'))
            ->get();
    }

    #[Computed]
    public function products()
    {
        if (! $this->restaurant) return collect();

        $query = Product::where('restaurant_id', $this->restaurant->id)
            ->available()
            ->notArchived()
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
        $this->modalError = null;

        $product = $this->modalProduct;
        // Pre-select the first available variant (variant choice is required when variants exist).
        $this->selectedVariant = $product?->variants->first()?->id;

        // Initialise addon selection: empty array for multi-choice groups, null for single.
        $this->selectedAddons = [];
        foreach ($product?->addonGroups ?? [] as $group) {
            $this->selectedAddons[$group->id] = $group->max_choices > 1 ? [] : null;
        }
    }

    public function closeProduct(): void
    {
        $this->showProductId = null;
        $this->reset('qty', 'notes', 'selectedVariant', 'selectedAddons', 'modalError');
    }

    #[Computed]
    public function modalProduct(): ?Product
    {
        if (! $this->showProductId) return null;

        // Fenced the same way the grid is: this restaurant, orderable, not
        // archived — addToCart trusts this lookup, so an id-only payload can't
        // reach a hidden or foreign product.
        return Product::where('restaurant_id', $this->restaurant?->id)
            ->available()
            ->notArchived()
            ->with([
                'variants' => fn ($q) => $q->available()->orderBy('sort_order'),
                'addonGroups' => fn ($q) => $q->active(),
                'addonGroups.activeOptions',
            ])->find($this->showProductId);
    }

    /** Flatten the selected addon ids into a plain list of ints. */
    private function selectedAddonOptionIds(): array
    {
        $ids = [];
        foreach ($this->selectedAddons as $value) {
            if (is_array($value)) {
                $ids = array_merge($ids, $value);
            } elseif ($value) {
                $ids[] = $value;
            }
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    #[Computed]
    public function modalUnitPrice(): float
    {
        $product = $this->modalProduct;
        if (! $product) return 0.0;

        $variant = $this->selectedVariant
            ? $product->variants->firstWhere('id', $this->selectedVariant)
            : null;

        $base = (float) ($variant?->price ?? $product->price);

        $chosen = $this->selectedAddonOptionIds();
        $addons = $product->addonGroups
            ->flatMap->activeOptions
            ->whereIn('id', $chosen)
            ->sum(fn ($o) => (float) $o->price);

        return $base + (float) $addons;
    }

    public function addToCart(int $productId): void
    {
        $restaurant = $this->restaurant;
        if (! $restaurant) return;

        $product = $this->modalProduct;
        if (! $product || $product->id !== $productId) return;

        $action = app(AddItemToCart::class);

        $this->modalError = $action->selectionError($product, $this->selectedVariant, $this->selectedAddons);
        if ($this->modalError) return;

        $action->handle(
            $restaurant,
            session()->getId(),
            $productId,
            $this->selectedVariant,
            $this->selectedAddonOptionIds(),
            $this->qty,
            $this->notes ?: null,
        );

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
        <x-store.product-card :product="$product" />
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
    @if($this->modalProduct)
    <x-store.product-modal :product="$this->modalProduct" :error="$modalError" :selected-variant="$selectedVariant"
                           :qty="$qty" :unit-price="$this->modalUnitPrice" />
    @endif

    @else
    <div class="py-20 text-center text-zinc-400">
        <p>Loja não encontrada.</p>
    </div>
    @endif
</div>
