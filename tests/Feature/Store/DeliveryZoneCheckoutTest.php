<?php

namespace Tests\Feature\Store;

use App\Actions\Orders\PlaceOrder;
use App\Models\Cart;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * The delivery fee comes from the chosen zone, so the zone must be an active
 * zone of this restaurant — an inactive or foreign zone must be refused, never
 * turned into free delivery.
 */
class DeliveryZoneCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private Product $product;
    private DeliveryZone $zone;
    private DeliveryZone $inactiveZone;
    private DeliveryZone $foreignZone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create([
            'accepts_delivery' => true,
            'accepts_pickup'   => true,
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

        $this->zone = $this->makeZone($this->restaurant, 'Centro', 8.00, true);
        $this->inactiveZone = $this->makeZone($this->restaurant, 'Zona Norte', 12.00, false);

        $foreign = Restaurant::factory()->create(['accepts_delivery' => true]);
        $this->foreignZone = $this->makeZone($foreign, 'Outra cidade', 3.00, true);
    }

    private function makeZone(Restaurant $restaurant, string $name, float $fee, bool $active): DeliveryZone
    {
        return DeliveryZone::create([
            'restaurant_id'     => $restaurant->id,
            'name'              => $name,
            'neighborhood'      => $name,
            'city'              => 'Sao Paulo',
            'fee'               => $fee,
            'estimated_minutes' => 40,
            'is_active'         => $active,
        ]);
    }

    private function addProductToCart(): void
    {
        Volt::test('store.menu')
            ->call('openProduct', $this->product->id)
            ->call('addToCart', $this->product->id)
            ->assertHasNoErrors();
    }

    private function deliveryCheckout(string $zoneId)
    {
        return Volt::test('store.checkout')
            ->set('deliveryType', 'delivery')
            ->set('deliveryZoneId', $zoneId)
            ->set('customerName', 'Joao Silva')
            ->set('street', 'Rua das Flores')
            ->set('addressNumber', '100')
            ->set('paymentMethod', 'cash');
    }

    public function test_checkout_page_lists_only_active_zones_of_the_restaurant(): void
    {
        $this->addProductToCart();

        // Hand the cart to the browser session of the real HTTP request.
        $sessionId = Str::random(40);
        Cart::query()->update(['session_id' => $sessionId]);

        $this->withCookie(config('session.cookie'), $sessionId)
            ->get(route('store.checkout'))
            ->assertOk()
            ->assertSee('Centro')
            ->assertDontSee('Zona Norte')
            ->assertDontSee('Outra cidade')
            ->assertDontSee('Undefined');
    }

    public function test_inactive_zone_is_rejected_and_no_order_is_created(): void
    {
        $this->addProductToCart();

        $this->deliveryCheckout((string) $this->inactiveZone->id)
            ->call('placeOrder')
            ->assertHasErrors(['deliveryZoneId' => 'exists']);

        $this->assertSame(0, Order::count());
    }

    public function test_zone_of_another_restaurant_is_rejected_and_no_order_is_created(): void
    {
        $this->addProductToCart();

        $this->deliveryCheckout((string) $this->foreignZone->id)
            ->call('placeOrder')
            ->assertHasErrors(['deliveryZoneId' => 'exists']);

        $this->assertSame(0, Order::count());
    }

    public function test_zone_deactivated_during_checkout_is_rejected(): void
    {
        $this->makeZone($this->restaurant, 'Zona Sul', 10.00, true);
        $this->addProductToCart();

        $checkout = $this->deliveryCheckout((string) $this->zone->id);

        $this->zone->update(['is_active' => false]);

        $checkout->call('placeOrder')
            ->assertHasErrors(['deliveryZoneId' => 'exists']);

        $this->assertSame(0, Order::count());
    }

    public function test_active_zone_of_the_restaurant_charges_its_fee(): void
    {
        $this->addProductToCart();

        $this->deliveryCheckout((string) $this->zone->id)
            ->call('placeOrder')
            ->assertHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertSame(8.0, (float) $order->delivery_fee);
        $this->assertSame(33.0, (float) $order->total);
    }

    public function test_place_order_refuses_an_invalid_zone_even_without_the_checkout(): void
    {
        foreach ([$this->inactiveZone->id, $this->foreignZone->id, null] as $zoneId) {
            $this->addProductToCart();

            try {
                app(PlaceOrder::class)->handle(Cart::firstOrFail(), [
                    'delivery_type'    => 'delivery',
                    'customer_name'    => 'Joao Silva',
                    'delivery_zone_id' => $zoneId,
                ]);
                $this->fail('PlaceOrder should refuse delivery zone '.var_export($zoneId, true).'.');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('zona de entrega', $e->getMessage());
            }

            $this->assertSame(0, Order::count());
        }
    }

    public function test_place_order_takes_the_fee_from_the_zone_not_from_the_caller(): void
    {
        $this->addProductToCart();

        $order = app(PlaceOrder::class)->handle(Cart::firstOrFail(), [
            'delivery_type'    => 'delivery',
            'customer_name'    => 'Joao Silva',
            'delivery_zone_id' => $this->zone->id,
            'delivery_fee'     => 0,
        ]);

        $this->assertSame(8.0, (float) $order->delivery_fee);
        $this->assertSame(33.0, (float) $order->total);
    }

    public function test_delivery_is_free_when_the_restaurant_has_no_active_zones(): void
    {
        DeliveryZone::where('restaurant_id', $this->restaurant->id)->update(['is_active' => false]);
        $this->addProductToCart();

        $this->deliveryCheckout('')
            ->call('placeOrder')
            ->assertHasNoErrors();

        $this->assertSame(0.0, (float) Order::firstOrFail()->delivery_fee);
    }
}
