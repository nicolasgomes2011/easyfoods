<?php

namespace Tests\Feature\Store;

use App\Enums\DeliveryType;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * The checkout starts on the first order type the store takes, in the order
 * delivery → pickup → dine-in, so a store without delivery never opens on a
 * hidden "Delivery" option with the address form showing.
 */
class CheckoutDefaultDeliveryTypeTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private Product $product;

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
    }

    private function accepts(bool $delivery, bool $pickup, bool $dineIn): void
    {
        $this->restaurant->update([
            'accepts_delivery' => $delivery,
            'accepts_pickup'   => $pickup,
            'accepts_dine_in'  => $dineIn,
        ]);
    }

    private function addProductToCart(): void
    {
        Volt::test('store.menu')
            ->call('openProduct', $this->product->id)
            ->call('addToCart', $this->product->id)
            ->assertHasNoErrors();
    }

    public function test_pickup_only_store_opens_on_pickup_without_the_address_form(): void
    {
        $this->accepts(delivery: false, pickup: true, dineIn: false);
        $this->addProductToCart();

        Volt::test('store.checkout')
            ->assertSet('deliveryType', 'pickup')
            ->assertSee('Retirada')
            ->assertDontSee('Endereço de entrega')
            ->assertDontSee('Selecione sua mesa');
    }

    public function test_dine_in_only_store_opens_on_dine_in(): void
    {
        $this->accepts(delivery: false, pickup: false, dineIn: true);
        $this->addProductToCart();

        Volt::test('store.checkout')
            ->assertSet('deliveryType', 'dine_in')
            ->assertSee('Mesa (salão)')
            ->assertSee('Selecione sua mesa')
            ->assertDontSee('Endereço de entrega');
    }

    public function test_store_without_delivery_opens_on_pickup_before_dine_in(): void
    {
        $this->accepts(delivery: false, pickup: true, dineIn: true);
        $this->addProductToCart();

        Volt::test('store.checkout')->assertSet('deliveryType', 'pickup');
    }

    public function test_store_with_delivery_keeps_opening_on_delivery(): void
    {
        $this->addProductToCart();

        Volt::test('store.checkout')
            ->assertSet('deliveryType', 'delivery')
            ->assertSee('Endereço de entrega');
    }

    public function test_pickup_only_store_takes_the_order_without_changing_the_type(): void
    {
        $this->accepts(delivery: false, pickup: true, dineIn: false);
        $this->addProductToCart();

        Volt::test('store.checkout')
            ->set('customerName', 'Joao Silva')
            ->set('paymentMethod', 'cash')
            ->call('placeOrder')
            ->assertHasNoErrors()
            ->assertSet('error', null);

        $this->assertSame(DeliveryType::Pickup, Order::sole()->delivery_type);
    }

    public function test_default_type_follows_delivery_pickup_dine_in_and_is_null_when_none(): void
    {
        $this->accepts(delivery: false, pickup: false, dineIn: true);
        $this->assertSame(DeliveryType::DineIn, $this->restaurant->fresh()->defaultDeliveryType());

        $this->accepts(delivery: false, pickup: false, dineIn: false);
        $this->assertNull($this->restaurant->fresh()->defaultDeliveryType());
    }

    public function test_checkout_page_of_a_pickup_only_store_has_no_address_form(): void
    {
        $this->accepts(delivery: false, pickup: true, dineIn: false);
        $this->addProductToCart();

        // Hand the cart to the browser session of the real HTTP request.
        $sessionId = Str::random(40);
        Cart::query()->update(['session_id' => $sessionId]);

        $this->withCookie(config('session.cookie'), $sessionId)
            ->get(route('store.checkout'))
            ->assertOk()
            ->assertSee('Retirada')
            ->assertDontSee('Endereço de entrega')
            ->assertDontSee('Undefined');
    }
}
