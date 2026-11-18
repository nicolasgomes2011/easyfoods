<?php

namespace Tests\Feature\Store;

use App\Enums\DeliveryType;
use App\Models\AddonGroup;
use App\Models\AddonOption;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartItemAddon;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * "Esvaziar carrinho" on /store/cart: removes every line (and its addons) of
 * the current session's cart only, keeps the cart's order type and table.
 */
class ClearCartTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private Product $product;
    private AddonOption $bacon;
    private AddonGroup $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create([
            'accepts_delivery' => true,
            'accepts_pickup'   => true,
            'accepts_dine_in'  => true,
        ]);

        $category = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name'          => 'Lanches',
            'slug'          => 'lanches',
            'is_active'     => true,
            'sort_order'    => 1,
        ]);

        $this->product = Product::create([
            'restaurant_id'       => $this->restaurant->id,
            'category_id'         => $category->id,
            'name'                => 'X-Burger',
            'price'               => 25.00,
            'availability_status' => 'available',
            'sort_order'          => 1,
            'is_featured'         => false,
        ]);

        $this->group = AddonGroup::create([
            'product_id'  => $this->product->id,
            'name'        => 'Extras',
            'required'    => false,
            'min_choices' => 0,
            'max_choices' => 3,
            'sort_order'  => 1,
            'is_active'   => true,
        ]);

        $this->bacon = AddonOption::create([
            'addon_group_id' => $this->group->id,
            'name'           => 'Bacon',
            'price'          => 3.00,
            'is_active'      => true,
            'sort_order'     => 1,
        ]);
    }

    private function addToOwnCart(int $qty = 1): Cart
    {
        Volt::test('store.menu')
            ->call('openProduct', $this->product->id)
            ->set('qty', $qty)
            ->set('selectedAddons.' . $this->group->id, [$this->bacon->id])
            ->call('addToCart', $this->product->id)
            ->assertHasNoErrors();

        return Cart::where('session_id', session()->getId())
            ->where('restaurant_id', $this->restaurant->id)
            ->firstOrFail();
    }

    private function cartWithItemFor(string $sessionId): Cart
    {
        $cart = Cart::create([
            'restaurant_id' => $this->restaurant->id,
            'session_id'    => $sessionId,
        ]);

        $item = CartItem::create([
            'cart_id'    => $cart->id,
            'product_id' => $this->product->id,
            'quantity'   => 1,
        ]);

        CartItemAddon::create([
            'cart_item_id'    => $item->id,
            'addon_option_id' => $this->bacon->id,
            'quantity'        => 1,
        ]);

        return $cart;
    }

    public function test_clearing_removes_every_item_and_addon_of_this_cart_only(): void
    {
        $own   = $this->addToOwnCart(2);
        $other = $this->cartWithItemFor('another-customer-session');

        $this->assertSame(1, CartItem::where('cart_id', $own->id)->count());

        Volt::test('store.cart')->call('clear')->assertHasNoErrors();

        $this->assertSame(0, CartItem::where('cart_id', $own->id)->count());
        $this->assertSame(1, CartItem::where('cart_id', $other->id)->count());
        $this->assertSame(1, CartItemAddon::count(), 'only the other cart addon remains');
        $this->assertSame(
            $other->items()->value('id'),
            CartItemAddon::value('cart_item_id')
        );
    }

    public function test_clearing_keeps_the_cart_order_type_and_table(): void
    {
        $table = DiningTable::create([
            'restaurant_id' => $this->restaurant->id,
            'number'        => 4,
            'status'        => 'free',
        ]);

        $own = $this->addToOwnCart();
        $own->update([
            'delivery_type'   => DeliveryType::DineIn,
            'dining_table_id' => $table->id,
        ]);

        Volt::test('store.cart')->call('clear');

        $own->refresh();
        $this->assertSame(DeliveryType::DineIn, $own->delivery_type);
        $this->assertSame($table->id, $own->dining_table_id);
    }

    public function test_cleared_cart_shows_the_empty_state_with_a_link_to_the_menu(): void
    {
        $this->addToOwnCart();

        Volt::test('store.cart')
            ->assertSee('Esvaziar carrinho')
            ->call('clear')
            ->assertSee('Seu carrinho está vazio')
            ->assertSee(route('store.menu'))
            ->assertDontSee('Esvaziar carrinho');
    }

    public function test_menu_floating_cart_counter_returns_to_zero(): void
    {
        $this->addToOwnCart(3);

        $this->assertSame(3, Volt::test('store.menu')->instance()->cartItemCount);

        Volt::test('store.cart')->call('clear');

        $this->assertSame(0, Volt::test('store.menu')->instance()->cartItemCount);
    }

    public function test_clear_button_asks_for_confirmation_before_calling_the_server(): void
    {
        $this->addToOwnCart();

        Volt::test('store.cart')
            ->assertSeeHtml('wire:click="clear"')
            ->assertSeeHtml('wire:confirm="Remover todos os itens?"');
    }

    public function test_clearing_without_a_cart_is_a_no_op(): void
    {
        $other = $this->cartWithItemFor('another-customer-session');

        Volt::test('store.cart')->call('clear')->assertHasNoErrors();

        $this->assertSame(1, CartItem::where('cart_id', $other->id)->count());
    }

    public function test_cart_page_renders_the_clear_button_over_http(): void
    {
        $sessionId = Str::random(40);
        $this->cartWithItemFor($sessionId);

        $this->withCookie(config('session.cookie'), $sessionId)
            ->get(route('store.cart'))
            ->assertOk()
            ->assertSee('Esvaziar carrinho')
            ->assertSee('wire:confirm="Remover todos os itens?"', false)
            ->assertDontSee('Undefined');
    }

    public function test_empty_cart_page_has_no_clear_button(): void
    {
        $this->get(route('store.cart'))
            ->assertOk()
            ->assertSee('Seu carrinho está vazio')
            ->assertDontSee('Esvaziar carrinho')
            ->assertDontSee('Undefined');
    }
}
