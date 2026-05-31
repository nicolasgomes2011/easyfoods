<?php
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Restaurant;

new #[Layout('components.layouts.customer')] class extends Component {

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
    public function subtotal(): float
    {
        if (! $this->cart) return 0.0;
        return $this->cart->items->sum(fn ($item) => $item->unitPrice() * $item->quantity);
    }

    public function increment(int $itemId): void
    {
        CartItem::where('id', $itemId)->where('cart_id', $this->cart?->id)->increment('quantity');
        unset($this->cart, $this->subtotal);
    }

    public function decrement(int $itemId): void
    {
        $item = CartItem::where('id', $itemId)->where('cart_id', $this->cart?->id)->first();
        if (! $item) return;

        if ($item->quantity <= 1) {
            $item->addons()->delete();
            $item->delete();
        } else {
            $item->decrement('quantity');
        }

        unset($this->cart, $this->subtotal);
    }

    public function remove(int $itemId): void
    {
        $item = CartItem::where('id', $itemId)->where('cart_id', $this->cart?->id)->first();
        if (! $item) return;
        $item->addons()->delete();
        $item->delete();
        unset($this->cart, $this->subtotal);
    }
}; ?>

<div>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-zinc-800">Carrinho</h1>
    </div>

    @if(! $this->cart || $this->cart->items->isEmpty())
    <div class="py-20 text-center">
        <svg class="size-14 text-zinc-200 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" /></svg>
        <p class="text-zinc-500 font-medium">Seu carrinho está vazio</p>
        <a href="{{ route('store.menu') }}" wire:navigate
           class="inline-block mt-4 text-sm text-orange-500 hover:text-orange-600 font-medium">
            Ver cardápio →
        </a>
    </div>
    @else
    <div class="space-y-3 mb-6">
        @foreach($this->cart->items as $item)
        <div class="bg-white border border-zinc-200 rounded-2xl p-4 flex gap-4">
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-zinc-800 truncate">{{ $item->product?->name ?? 'Produto' }}</p>
                @if($item->variant)
                <p class="text-xs text-zinc-500">{{ $item->variant->name }}</p>
                @endif
                @if($item->notes)
                <p class="text-xs text-zinc-400 italic mt-0.5">{{ $item->notes }}</p>
                @endif
                <p class="text-sm font-bold text-zinc-700 mt-1">R$ {{ number_format($item->unitPrice(), 2, ',', '.') }}</p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <button wire:click="decrement({{ $item->id }})"
                        class="size-7 flex items-center justify-center rounded-full border border-zinc-300 text-zinc-500 hover:bg-zinc-100 transition">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" /></svg>
                </button>
                <span class="text-sm font-semibold text-zinc-800 w-4 text-center">{{ $item->quantity }}</span>
                <button wire:click="increment({{ $item->id }})"
                        class="size-7 flex items-center justify-center rounded-full border border-zinc-300 text-zinc-500 hover:bg-zinc-100 transition">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                </button>
                <button wire:click="remove({{ $item->id }})"
                        class="ml-1 text-zinc-400 hover:text-red-400 transition">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                </button>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Order summary --}}
    <div class="bg-white border border-zinc-200 rounded-2xl p-5 mb-4">
        <h2 class="text-sm font-semibold text-zinc-700 mb-3">Resumo</h2>
        <div class="flex justify-between text-sm text-zinc-600 mb-1">
            <span>Subtotal</span>
            <span>R$ {{ number_format($this->subtotal, 2, ',', '.') }}</span>
        </div>
        <div class="flex justify-between text-sm text-zinc-400 mb-3">
            <span>Taxa de entrega</span>
            <span>Calculada no checkout</span>
        </div>
        <div class="border-t border-zinc-100 pt-3 flex justify-between font-bold text-zinc-800">
            <span>Total parcial</span>
            <span>R$ {{ number_format($this->subtotal, 2, ',', '.') }}</span>
        </div>
    </div>

    <div class="flex flex-col gap-3">
        <a href="{{ route('store.checkout') }}" wire:navigate
           class="w-full py-3.5 bg-orange-500 hover:bg-orange-600 text-white text-center font-semibold rounded-2xl transition">
            Ir para o checkout
        </a>
        <a href="{{ route('store.menu') }}" wire:navigate
           class="w-full py-3.5 bg-zinc-100 hover:bg-zinc-200 text-zinc-600 text-center text-sm rounded-2xl transition">
            Continuar comprando
        </a>
    </div>
    @endif
</div>
