<?php

namespace Tests\Feature\Store;

use App\Actions\Cart\AddItemToCart;
use App\Actions\Orders\PlaceOrder;
use App\Exceptions\CartItemNotAddable;
use App\Exceptions\OrderNotPlaceable;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * The quantity chosen in the menu modal is client-controlled, so the server
 * must only accept 1..CartItem::MAX_QUANTITY per cart line — when the item
 * enters the cart (menu and action), when it is merged into an equal line,
 * and again when the order is placed (cart lines already stored).
 */
class CartQuantityValidationTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private Product $burger;
    private Product $soda;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create(['accepts_pickup' => true]);

        $category = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name'          => 'Lanches',
            'slug'          => 'lanches',
            'is_active'     => true,
            'sort_order'    => 1,
        ]);

        $this->burger = $this->makeProduct($category, 'Burger', 30.00);
        $this->soda = $this->makeProduct($category, 'Soda', 5.00);
    }

    private function makeProduct(Category $category, string $name, float $price): Product
    {
        return Product::create([
            'restaurant_id'       => $this->restaurant->id,
            'category_id'         => $category->id,
            'name'                => $name,
            'price'               => $price,
            'availability_status' => 'available',
            'sort_order'          => 1,
            'is_featured'         => false,
        ]);
    }

    private function addFromMenu(Product $product, int $qty)
    {
        return Volt::test('store.menu')
            ->call('openProduct', $product->id)
            ->set('qty', $qty)
            ->call('addToCart', $product->id);
    }

    private function addWithAction(Product $product, int $quantity, string $session = 'session-q'): CartItem
    {
        return app(AddItemToCart::class)->handle($this->restaurant, $session, $product, null, [], $quantity, null);
    }

    private function placePickupOrder(Cart $cart): Order
    {
        return app(PlaceOrder::class)->handle($cart, [
            'delivery_type' => 'pickup',
            'customer_name' => 'Cliente Teste',
        ]);
    }

    private function assertModalRejected($component): void
    {
        $component->assertSet('modalError', fn ($v) => is_string($v) && $v !== '');
    }

    // ── Menu modal ───────────────────────────────────────────────────────────

    public function test_menu_refuses_zero_quantity_and_saves_nothing(): void
    {
        $this->assertModalRejected($this->addFromMenu($this->soda, 0));

        $this->assertSame(0, CartItem::count());
    }

    public function test_menu_refuses_negative_quantity_and_saves_nothing(): void
    {
        $component = $this->addFromMenu($this->soda, -10);

        $this->assertModalRejected($component);
        $component->assertSet('showProductId', $this->soda->id); // modal stays open with the message
        $this->assertSame(0, CartItem::count());
    }

    public function test_menu_refuses_quantity_above_the_ceiling(): void
    {
        $this->assertModalRejected($this->addFromMenu($this->soda, CartItem::MAX_QUANTITY + 1));

        $this->assertSame(0, CartItem::count());
    }

    public function test_menu_accepts_the_ceiling(): void
    {
        $this->addFromMenu($this->soda, CartItem::MAX_QUANTITY)->assertSet('modalError', null);

        $this->assertSame(CartItem::MAX_QUANTITY, CartItem::firstOrFail()->quantity);
    }

    public function test_reported_scenario_keeps_the_order_total_right(): void
    {
        // 2× Burger normally, then Soda with −10 from the console.
        $this->addFromMenu($this->burger, 2)->assertSet('modalError', null);
        $this->assertModalRejected($this->addFromMenu($this->soda, -10));

        $cart = Cart::firstOrFail();
        $this->assertSame(1, $cart->items()->count());

        $order = $this->placePickupOrder($cart);

        $this->assertSame('60.00', number_format((float) $order->subtotal, 2, '.', ''));
        $this->assertSame('60.00', number_format((float) $order->total, 2, '.', ''));
        $this->assertSame(0, OrderItem::where('quantity', '<', 1)->count());
    }

    public function test_menu_merge_above_the_ceiling_is_refused_and_keeps_the_line(): void
    {
        $this->addFromMenu($this->soda, 60)->assertSet('modalError', null);
        $this->assertModalRejected($this->addFromMenu($this->soda, 50));

        $this->assertSame(1, CartItem::count());
        $this->assertSame(60, CartItem::firstOrFail()->quantity);
    }

    // ── Action (any caller) ──────────────────────────────────────────────────

    public function test_action_refuses_zero_negative_and_above_ceiling_without_touching_the_database(): void
    {
        foreach ([0, -3, CartItem::MAX_QUANTITY + 1] as $quantity) {
            try {
                $this->addWithAction($this->soda, $quantity);
                $this->fail("AddItemToCart should refuse quantity {$quantity}.");
            } catch (CartItemNotAddable $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }

        $this->assertSame(0, Cart::count());
        $this->assertSame(0, CartItem::count());
    }

    public function test_merging_into_an_equal_line_never_decreases_it(): void
    {
        $line = $this->addWithAction($this->soda, 2);

        foreach ([-1, 0] as $quantity) {
            try {
                $this->addWithAction($this->soda, $quantity);
                $this->fail("AddItemToCart should refuse merging quantity {$quantity}.");
            } catch (CartItemNotAddable) {
                // expected
            }
        }

        $this->assertSame(2, $line->fresh()->quantity);
    }

    public function test_merging_adds_up_to_the_ceiling_and_refuses_past_it(): void
    {
        $line = $this->addWithAction($this->soda, 2);

        $this->addWithAction($this->soda, 3);
        $this->assertSame(5, $line->fresh()->quantity);

        try {
            $this->addWithAction($this->soda, CartItem::MAX_QUANTITY - 4);
            $this->fail('AddItemToCart should refuse a merge past the ceiling.');
        } catch (CartItemNotAddable) {
            // expected
        }
        $this->assertSame(5, $line->fresh()->quantity);

        $this->addWithAction($this->soda, CartItem::MAX_QUANTITY - 5);
        $this->assertSame(CartItem::MAX_QUANTITY, $line->fresh()->quantity);
        $this->assertSame(1, CartItem::count());
    }

    // ── Cart page ────────────────────────────────────────────────────────────

    public function test_cart_page_plus_button_stops_at_the_ceiling(): void
    {
        $this->addFromMenu($this->soda, CartItem::MAX_QUANTITY)->assertSet('modalError', null);
        $line = CartItem::firstOrFail();

        Volt::test('store.cart')->call('increment', $line->id);

        $this->assertSame(CartItem::MAX_QUANTITY, $line->fresh()->quantity);
    }

    // ── Placing the order (stored carts) ─────────────────────────────────────

    public function test_place_order_refuses_a_stored_line_with_negative_quantity(): void
    {
        $this->addWithAction($this->burger, 2);
        $cart = Cart::firstOrFail();
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $this->soda->id, 'quantity' => -10]);

        $this->assertPlaceRefused($cart);
    }

    public function test_place_order_refuses_a_stored_line_with_zero_quantity(): void
    {
        $this->addWithAction($this->burger, 1);
        $cart = Cart::firstOrFail();
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $this->soda->id, 'quantity' => 0]);

        $this->assertPlaceRefused($cart);
    }

    public function test_place_order_totals_valid_quantities(): void
    {
        $this->addWithAction($this->burger, 2);
        $this->addWithAction($this->soda, 1);

        $order = $this->placePickupOrder(Cart::firstOrFail());

        $this->assertSame('65.00', number_format((float) $order->total, 2, '.', ''));
        $this->assertSame([2, 1], $order->items()->orderBy('id')->pluck('quantity')->map('intval')->all());
    }

    // ── Real routes ──────────────────────────────────────────────────────────

    public function test_real_menu_and_cart_routes_render(): void
    {
        $this->get(route('store.menu'))
            ->assertOk()
            ->assertSee('Burger')
            ->assertDontSee('Undefined');

        $this->get(route('store.cart'))
            ->assertOk()
            ->assertDontSee('Undefined');
    }

    private function assertPlaceRefused(Cart $cart): void
    {
        try {
            $this->placePickupOrder($cart);
            $this->fail('PlaceOrder should refuse a cart line with quantity below 1.');
        } catch (OrderNotPlaceable $e) {
            $this->assertStringContainsString('Soda', $e->getMessage());
        }

        $this->assertSame(0, Order::count());
        $this->assertSame(0, OrderItem::count());
    }
}
