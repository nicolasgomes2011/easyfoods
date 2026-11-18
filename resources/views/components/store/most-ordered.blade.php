{{--
    "Mais pedidos" section of the customer menu (store.menu): the products from
    App\Support\MostOrderedProducts::forMenu(), already limited to what can be
    ordered now. Renders nothing when the list is empty. Hidden while the
    menu's quick search (Alpine `term`) is in use, so no product shows twice.
--}}
@props(['products'])

@if($products->isNotEmpty())
<section data-most-ordered x-show="! term" class="mb-8">
    <h2 class="text-lg font-semibold text-zinc-800 mb-3">Mais pedidos</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($products as $product)
        <div data-most-ordered-product="{{ $product->id }}" wire:key="most-ordered-{{ $product->id }}">
            <x-store.product-card :product="$product" />
        </div>
        @endforeach
    </div>
</section>
@endif
