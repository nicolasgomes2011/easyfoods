<?php

namespace Tests\Feature\Store;

use App\Enums\OrderStatus;
use App\Models\AddonGroup;
use App\Models\AddonOption;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * End-to-end verification of the customer ordering flow (storefront -> cart ->
 * checkout -> tracking), including variant + addon selection and zone-based
 * delivery fee. Phase 3 shipped with no tests; this is the regression net.
 */
class CustomerOrderingFlowTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private Category $category;
    private Product $product;
    private DiningTable $table;
    private DeliveryZone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create([
            'accepts_delivery' => true,
            'accepts_pickup'   => true,
            'accepts_dine_in'  => true,
        ]);

        $this->category = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name'          => 'Lanches',
            'slug'          => 'lanches',
            'is_active'     => true,
            'sort_order'    => 1,
        ]);

        $this->product = Product::create([
            'restaurant_id'       => $this->restaurant->id,
            'category_id'         => $this->category->id,
            'name'                => 'X-Burger',
            'description'         => 'Pao, carne e queijo',
            'price'               => 25.00,
            'availability_status' => 'available',
            'sort_order'          => 1,
            'is_featured'         => false,
        ]);

        $this->table = DiningTable::create([
            'restaurant_id' => $this->restaurant->id,
            'number'        => 1,
            'status'        => 'free',
        ]);

        $this->zone = DeliveryZone::create([
            'restaurant_id'     => $this->restaurant->id,
            'name'              => 'Centro',
            'neighborhood'      => 'Centro',
            'city'              => 'Sao Paulo',
            'fee'               => 7.50,
            'estimated_minutes' => 40,
            'is_active'         => true,
        ]);
    }

    private function addSimpleProductToCart(int $qty = 1): void
    {
        Volt::test('store.menu')
            ->call('openProduct', $this->product->id)
            ->set('qty', $qty)
            ->call('addToCart', $this->product->id)
            ->assertHasNoErrors();
    }

    public function test_public_storefront_pages_render(): void
    {
        $this->get(route('store.home'))->assertOk();
        $this->get(route('store.menu'))->assertOk()->assertSee('X-Burger');
        $this->get(route('store.cart'))->assertOk();
    }

    public function test_checkout_redirects_to_cart_when_empty(): void
    {
        $this->get(route('store.checkout'))->assertRedirect(route('store.cart'));
    }

    public function test_product_can_be_added_to_cart_from_menu(): void
    {
        $this->addSimpleProductToCart(2);

        $cart = Cart::where('restaurant_id', $this->restaurant->id)->first();
        $this->assertNotNull($cart);
        $this->assertSame(
            2,
            CartItem::where('cart_id', $cart->id)->where('product_id', $this->product->id)->value('quantity')
        );
    }

    public function test_delivery_order_is_placed_with_frozen_snapshot_history_and_zone_fee(): void
    {
        $this->addSimpleProductToCart(2);

        Volt::test('store.checkout')
            ->set('deliveryType', 'delivery')
            ->set('deliveryZoneId', (string) $this->zone->id)
            ->set('customerName', 'Joao Silva')
            ->set('customerPhone', '(11) 99999-0000')
            ->set('street', 'Rua das Flores')
            ->set('addressNumber', '100')
            ->set('paymentMethod', 'cash')
            ->call('placeOrder')
            ->assertHasNoErrors();

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertSame(OrderStatus::PendingConfirmation, $order->status);
        $this->assertSame(50.0, (float) $order->subtotal);     // 25.00 * 2
        $this->assertSame(7.5, (float) $order->delivery_fee);  // from the selected zone
        $this->assertSame(57.5, (float) $order->total);
        $this->assertSame('00001', $order->number);
        $this->assertSame('Joao Silva', $order->customer_name);
        $this->assertSame('Rua das Flores', $order->delivery_address_street);
        $this->assertSame('Centro', $order->delivery_address_neighborhood); // filled from the zone
        $this->assertSame('Sao Paulo', $order->delivery_address_city);

        // Frozen item snapshot (name + price copied, not referenced).
        $item = OrderItem::where('order_id', $order->id)->first();
        $this->assertSame('X-Burger', $item->product_name);
        $this->assertSame(25.0, (float) $item->unit_price);
        $this->assertSame(2, $item->quantity);

        // Append-only status history seeded with the opening transition.
        $history = OrderStatusHistory::where('order_id', $order->id)->get();
        $this->assertCount(1, $history);
        $this->assertNull($history->first()->from_status);
        $this->assertSame(OrderStatus::PendingConfirmation, $history->first()->to_status);

        // Cart fully cleared after checkout.
        $this->assertSame(0, Cart::count());
        $this->assertSame(0, CartItem::count());

        // Tracking page renders for the new order — addressed by the public token
        // (not the enumerable sequential number), while still showing the number.
        $this->get(route('store.order.tracking', $order->token))
            ->assertOk()
            ->assertSee('00001');
    }

    public function test_delivery_requires_a_zone_when_zones_are_configured(): void
    {
        $this->addSimpleProductToCart();

        Volt::test('store.checkout')
            ->set('deliveryType', 'delivery')
            ->set('deliveryZoneId', '')
            ->set('customerName', 'Joao')
            ->set('street', 'Rua A')
            ->set('addressNumber', '10')
            ->set('paymentMethod', 'cash')
            ->call('placeOrder')
            ->assertHasErrors('deliveryZoneId');

        $this->assertSame(0, Order::count());
    }

    public function test_pickup_order_does_not_require_address_and_is_free(): void
    {
        $this->addSimpleProductToCart();

        Volt::test('store.checkout')
            ->set('deliveryType', 'pickup')
            ->set('customerName', 'Maria')
            ->set('paymentMethod', 'cash')
            ->call('placeOrder')
            ->assertHasNoErrors();

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertSame(0.0, (float) $order->delivery_fee);
        $this->assertNull($order->delivery_address_street);
    }

    public function test_dine_in_order_requires_a_table(): void
    {
        $this->addSimpleProductToCart();

        Volt::test('store.checkout')
            ->set('deliveryType', 'dine_in')
            ->set('customerName', 'Carlos')
            ->set('paymentMethod', 'cash')
            ->set('tableId', '')
            ->call('placeOrder')
            ->assertHasErrors('tableId');

        $this->assertSame(0, Order::count());
    }

    public function test_variant_selection_drives_the_price_and_is_snapshotted(): void
    {
        $small = ProductVariant::create([
            'product_id'          => $this->product->id,
            'name'                => 'Pequeno',
            'price'               => 20.00,
            'availability_status' => 'available',
            'sort_order'          => 1,
        ]);
        $large = ProductVariant::create([
            'product_id'          => $this->product->id,
            'name'                => 'Grande',
            'price'               => 35.00,
            'availability_status' => 'available',
            'sort_order'          => 2,
        ]);

        Volt::test('store.menu')
            ->call('openProduct', $this->product->id)
            ->set('selectedVariant', $large->id)
            ->call('addToCart', $this->product->id)
            ->assertHasNoErrors();

        $cartItem = CartItem::first();
        $this->assertSame($large->id, $cartItem->product_variant_id);
        $this->assertSame(35.0, $cartItem->lineTotal());

        Volt::test('store.checkout')
            ->set('deliveryType', 'pickup')
            ->set('customerName', 'Ana')
            ->set('paymentMethod', 'cash')
            ->call('placeOrder')
            ->assertHasNoErrors();

        $item = OrderItem::first();
        $this->assertSame('Grande', $item->variant_name);
        $this->assertSame(35.0, (float) $item->unit_price);
        $this->assertSame(35.0, (float) $item->subtotal);
    }

    public function test_addons_are_charged_and_snapshotted(): void
    {
        $group = AddonGroup::create([
            'product_id'  => $this->product->id,
            'name'        => 'Extras',
            'required'    => false,
            'min_choices' => 0,
            'max_choices' => 3,
            'sort_order'  => 1,
            'is_active'   => true,
        ]);
        $bacon = AddonOption::create([
            'addon_group_id' => $group->id,
            'name'           => 'Bacon',
            'price'          => 3.00,
            'is_active'      => true,
            'sort_order'     => 1,
        ]);
        $cheese = AddonOption::create([
            'addon_group_id' => $group->id,
            'name'           => 'Cheddar',
            'price'          => 5.00,
            'is_active'      => true,
            'sort_order'     => 2,
        ]);

        Volt::test('store.menu')
            ->call('openProduct', $this->product->id)
            ->set('selectedAddons.' . $group->id, [$bacon->id, $cheese->id])
            ->call('addToCart', $this->product->id)
            ->assertHasNoErrors();

        // base 25 + bacon 3 + cheddar 5 = 33
        $cartItem = CartItem::with('addons')->first();
        $this->assertCount(2, $cartItem->addons);
        $this->assertSame(33.0, $cartItem->lineTotal());

        Volt::test('store.checkout')
            ->set('deliveryType', 'pickup')
            ->set('customerName', 'Bruno')
            ->set('paymentMethod', 'cash')
            ->call('placeOrder')
            ->assertHasNoErrors();

        $order = Order::first();
        $this->assertSame(33.0, (float) $order->subtotal);
        $this->assertSame(33.0, (float) $order->total);

        $item = OrderItem::with('addons')->first();
        $this->assertSame(25.0, (float) $item->unit_price); // base price stays unblended
        $this->assertSame(33.0, (float) $item->subtotal);   // line subtotal folds addons
        $this->assertCount(2, $item->addons);
        $this->assertEqualsCanonicalizing(
            ['Bacon', 'Cheddar'],
            $item->addons->pluck('addon_option_name')->all()
        );
    }

    public function test_required_addon_group_blocks_adding_without_a_choice(): void
    {
        $group = AddonGroup::create([
            'product_id'  => $this->product->id,
            'name'        => 'Ponto da carne',
            'required'    => true,
            'min_choices' => 1,
            'max_choices' => 1,
            'sort_order'  => 1,
            'is_active'   => true,
        ]);
        AddonOption::create([
            'addon_group_id' => $group->id,
            'name'           => 'Bem passado',
            'price'          => 0.00,
            'is_active'      => true,
            'sort_order'     => 1,
        ]);

        Volt::test('store.menu')
            ->call('openProduct', $this->product->id)
            ->call('addToCart', $this->product->id)
            ->assertSet('modalError', fn ($v) => $v !== null);

        $this->assertSame(0, CartItem::count());
    }
}
