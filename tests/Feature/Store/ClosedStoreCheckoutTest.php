<?php

namespace Tests\Feature\Store;

use App\Actions\Orders\PlaceOrder;
use App\Exceptions\OrderNotPlaceable;
use App\Models\Cart;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\OperatingHour;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Orders cannot be placed while the store is closed (same opening rule as the
 * menu header) or for an order type the store does not take. Only domain
 * reasons reach the customer; any other failure shows a generic message.
 *
 * 2026-11-11 is a Wednesday (weekday 3); 2026-11-12 is a Thursday (weekday 4).
 */
class ClosedStoreCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->withoutHours()->create([
            'accepts_delivery' => true,
            'accepts_pickup'   => true,
            'accepts_dine_in'  => true,
        ]);

        // Wednesday 11:00–23:00; Thursday has no hours at all.
        OperatingHour::create([
            'restaurant_id' => $this->restaurant->id,
            'weekday'       => 3,
            'opens_at'      => '11:00',
            'closes_at'     => '23:00',
            'is_closed'     => false,
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function addProductToCart(): void
    {
        Volt::test('store.menu')
            ->call('openProduct', $this->product->id)
            ->call('addToCart', $this->product->id)
            ->assertHasNoErrors();
    }

    private function pickupCheckout()
    {
        return Volt::test('store.checkout')
            ->set('deliveryType', 'pickup')
            ->set('customerName', 'Joao Silva')
            ->set('paymentMethod', 'cash');
    }

    public function test_order_is_refused_while_the_store_is_closed(): void
    {
        Carbon::setTestNow('2026-11-11 10:30');
        $this->addProductToCart();

        $this->pickupCheckout()
            ->call('placeOrder')
            ->assertHasNoErrors()
            ->assertSet('error', 'A loja está fechada agora. Confira o horário de funcionamento no cardápio.');

        $this->assertSame(0, Order::count());
    }

    public function test_order_is_refused_on_a_day_without_hours(): void
    {
        Carbon::setTestNow('2026-11-12 12:00');
        $this->addProductToCart();

        $this->pickupCheckout()->call('placeOrder');

        $this->assertSame(0, Order::count());
    }

    public function test_order_goes_through_while_the_store_is_open(): void
    {
        Carbon::setTestNow('2026-11-11 12:00');
        $this->addProductToCart();

        $this->pickupCheckout()
            ->call('placeOrder')
            ->assertSet('error', null);

        $this->assertSame(1, Order::count());
    }

    public function test_dine_in_order_is_refused_when_the_store_does_not_take_dine_in(): void
    {
        Carbon::setTestNow('2026-11-11 12:00');
        $this->restaurant->update(['accepts_dine_in' => false]);
        $table = DiningTable::create([
            'restaurant_id' => $this->restaurant->id,
            'number'        => 7,
            'uuid'          => (string) Str::uuid(),
            'status'        => 'free',
            'capacity'      => 4,
        ]);
        $this->addProductToCart();

        Volt::test('store.checkout')
            ->set('deliveryType', 'dine_in')
            ->set('tableId', (string) $table->id)
            ->set('customerName', 'Joao Silva')
            ->set('paymentMethod', 'cash')
            ->call('placeOrder')
            ->assertSet('error', 'Esta loja não está aceitando pedidos de mesa no momento.');

        $this->assertSame(0, Order::count());
    }

    public function test_place_order_refuses_an_order_type_the_store_does_not_take(): void
    {
        Carbon::setTestNow('2026-11-11 12:00');
        $this->restaurant->update(['accepts_pickup' => false]);
        $this->addProductToCart();

        $this->expectException(OrderNotPlaceable::class);
        $this->expectExceptionMessage('Esta loja não está aceitando pedidos de retirada no momento.');

        app(PlaceOrder::class)->handle(Cart::firstOrFail(), [
            'delivery_type' => 'pickup',
            'customer_name' => 'Joao Silva',
        ]);
    }

    public function test_unexpected_error_shows_a_generic_message_and_is_reported(): void
    {
        Carbon::setTestNow('2026-11-11 12:00');
        Exceptions::fake();
        $this->addProductToCart();

        $this->app->bind(PlaceOrder::class, fn () => new class extends PlaceOrder {
            public function handle(Cart $cart, array $data): Order
            {
                throw new \RuntimeException('SQLSTATE[HY000]: internal detail');
            }
        });

        $this->pickupCheckout()
            ->call('placeOrder')
            ->assertSet('error', 'Não foi possível concluir o pedido. Tente novamente.');

        Exceptions::assertReported(\RuntimeException::class);
        $this->assertSame(0, Order::count());
    }

    public function test_checkout_page_shows_the_closed_notice_and_disables_confirm(): void
    {
        Carbon::setTestNow('2026-11-11 10:30');
        $this->addProductToCart();

        // Hand the cart to the browser session of the real HTTP request.
        $sessionId = Str::random(40);
        Cart::query()->update(['session_id' => $sessionId]);

        $this->withCookie(config('session.cookie'), $sessionId)
            ->get(route('store.checkout'))
            ->assertOk()
            ->assertSee('A loja está fechada agora')
            ->assertSee('data-store-closed', false)
            ->assertDontSee('Undefined');
    }

    public function test_checkout_page_has_no_closed_notice_while_open(): void
    {
        Carbon::setTestNow('2026-11-11 12:00');
        $this->addProductToCart();

        $sessionId = Str::random(40);
        Cart::query()->update(['session_id' => $sessionId]);

        $this->withCookie(config('session.cookie'), $sessionId)
            ->get(route('store.checkout'))
            ->assertOk()
            ->assertDontSee('A loja está fechada agora')
            ->assertDontSee('data-store-closed', false);
    }
}
