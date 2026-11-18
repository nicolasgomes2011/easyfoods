<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use App\Actions\Cart\AddItemToCart;
use App\Exceptions\CartItemNotAddable;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use App\Support\CurrentRestaurant;
use App\Support\MostOrderedProducts;

new #[Layout('components.layouts.customer')] class extends Component {

    /** Deep link (?category=slug): the whole menu opens scrolled to that category's section. */
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

    /** "Mais pedidos": ranked products that can be ordered now (MostOrderedProducts); empty for a new restaurant. */
    #[Computed]
    public function mostOrdered()
    {
        if (! $this->restaurant) return collect();

        return MostOrderedProducts::forMenu($this->restaurant->id, $this->products, $this->categories->pluck('id'));
    }

    #[Computed]
    public function categories()
    {
        if (! $this->restaurant) return collect();
        // A tab and a section show while the category has any non-archived product:
        // paused ones stay on the menu (greyed out), so the category stays too.
        return Category::where('restaurant_id', $this->restaurant->id)
            ->active()->ordered()
            ->whereHas('products', fn ($q) => $q->notArchived())
            ->get();
    }

    #[Computed]
    public function products()
    {
        if (! $this->restaurant) return collect();

        // Paused / out-of-stock products are listed (greyed out, no add action);
        // only archived ones are hidden. Ordering stays fenced by modalProduct().
        $query = Product::where('restaurant_id', $this->restaurant->id)
            ->notArchived()
            ->with(['category', 'variants'])
            ->ordered();

        return $query->get();
    }

    /** Products of each category section, keyed by category id (one query for the whole menu). */
    #[Computed]
    public function productsByCategory()
    {
        return $this->products->groupBy('category_id');
    }

    #[Computed]
    public function cartItemCount(): int
    {
        $cart = $this->currentCart();
        return $cart ? $cart->items()->sum('quantity') : 0;
    }

    /** Table bound by the table QR code (/mesa/{uuid}), or null outside table mode. */
    #[Computed]
    public function seatedTable(): ?\App\Models\DiningTable
    {
        return \App\Support\TableMode::table($this->restaurant);
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

        // qty is client-controlled (the − / + buttons only limit it in the browser).
        $this->modalError = $action->quantityError($this->qty)
            ?? $action->selectionError($product, $this->selectedVariant, $this->selectedAddons);
        if ($this->modalError) return;

        try {
            $action->handle(
                $restaurant,
                session()->getId(),
                $product,
                $this->selectedVariant,
                $this->selectedAddons,
                $this->qty,
                $this->notes ?: null,
            );
        } catch (CartItemNotAddable $e) {
            // e.g. merging into the equal line would pass the ceiling
            $this->modalError = $e->getMessage();

            return;
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

        <x-store.order-type-badges :restaurant="$this->restaurant" :table="$this->seatedTable" />

        {{-- Open / closed now + today's hours --}}
        @php($todayHours = $this->restaurant->hoursOn())
        <div class="flex flex-wrap items-center gap-2 mt-2 text-xs">
            @if($this->restaurant->isOpenAt())
            <span data-store-status="open" class="rounded-full bg-green-100 px-2.5 py-0.5 font-semibold text-green-700">Aberto</span>
            @else
            <span data-store-status="closed" class="rounded-full bg-zinc-200 px-2.5 py-0.5 font-semibold text-zinc-600">Fechado</span>
            @endif
            <span class="text-zinc-500">{{ $todayHours?->isOpenDay() ? 'Hoje: '.$todayHours->rangeLabel() : 'Fechado hoje' }}</span>
        </div>
    </div>

    @if($this->categories->isEmpty())
    <div class="py-16 text-center text-zinc-400 text-sm">
        Nenhum produto disponível no momento.
    </div>
    @else
    {{-- One page, a section per category: the tabs scroll to it in the browser (no server request)
         and the tab of the section in view is highlighted. The quick search (q) filters products,
         sections and tabs in the browser too, against each product's accent-free data-search. --}}
    <div x-data="{
            active: @js($this->categories->first()->slug),
            q: '',
            go(slug) {
                this.active = slug;
                document.getElementById('cat-' + slug)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            },
            get term() {
                return this.q.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/\s+/g, ' ').trim();
            },
            matches(text) { return ! this.term || (text ?? '').includes(this.term); },
            anyMatch(el) { return ! this.term || [...(el?.querySelectorAll('[data-search]') ?? [])].some(p => this.matches(p.dataset.search)); },
         }"
         x-init="
            const spy = new IntersectionObserver(entries => entries.forEach(e => { if (e.isIntersecting) active = e.target.dataset.category }), { rootMargin: '-120px 0px -60% 0px' });
            $el.querySelectorAll('[data-category]').forEach(section => spy.observe(section));
            if ($el.dataset.scrollTo) $nextTick(() => go($el.dataset.scrollTo));
         "
         @if($category) data-scroll-to="{{ $category }}" @endif>
        <x-store.menu-search />

        <div class="sticky top-14 z-40 -mx-4 px-4 py-2 mb-5 bg-zinc-50 flex gap-2 overflow-x-auto scrollbar-hide">
            @foreach($this->categories as $cat)
            <button type="button" data-category-tab="{{ $cat->slug }}" @click="go(@js($cat->slug))"
                    x-show="anyMatch(document.getElementById(@js('cat-'.$cat->slug)))"
                    :class="active === @js($cat->slug) ? 'bg-orange-500 text-white' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200'"
                    class="shrink-0 rounded-full px-3.5 py-1.5 text-sm font-medium transition">
                {{ $cat->name }}
            </button>
            @endforeach
        </div>

        <x-store.most-ordered :products="$this->mostOrdered" />

        @foreach($this->categories as $cat)
        <section id="cat-{{ $cat->slug }}" data-category="{{ $cat->slug }}" x-show="anyMatch($el)" class="mb-8 scroll-mt-32">
            <h2 class="text-lg font-semibold text-zinc-800 mb-3">{{ $cat->name }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($this->productsByCategory->get($cat->id, collect()) as $product)
                <div data-search="{{ $product->searchText() }}" x-show="matches($el.dataset.search)">
                    <x-store.product-card :product="$product" :most-ordered="$this->mostOrdered->contains('id', $product->id)" />
                </div>
                @endforeach
            </div>
        </section>
        @endforeach

        <p x-show="! anyMatch($root)" style="display: none" class="py-16 text-center text-zinc-400 text-sm">
            Nenhum produto encontrado.
        </p>
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
