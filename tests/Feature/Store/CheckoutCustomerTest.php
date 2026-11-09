<?php

namespace Tests\Feature\Store;

use App\Actions\Customers\ResolveCheckoutCustomer;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Guest checkout has no customer login, so the phone typed at checkout is what
 * links orders to a customer of the restaurant. Without it the admin Customers
 * screen stays empty in real use.
 */
class CheckoutCustomerTest extends TestCase
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

    private function placePickupOrder(string $name, ?string $phone): void
    {
        Volt::test('store.menu')
            ->call('openProduct', $this->product->id)
            ->set('qty', 1)
            ->call('addToCart', $this->product->id)
            ->assertHasNoErrors();

        Volt::test('store.checkout')
            ->set('deliveryType', 'pickup')
            ->set('customerName', $name)
            ->set('customerPhone', $phone ?? '')
            ->set('paymentMethod', 'cash')
            ->call('placeOrder')
            ->assertHasNoErrors();
    }

    private function actAsAdmin(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    public function test_two_orders_with_the_same_phone_make_one_customer_with_two_orders(): void
    {
        $this->placePickupOrder('Joao Silva', '(11) 98765-4321');
        $this->placePickupOrder('Joao Silva Santos', '11 98765 4321');

        $this->assertSame(2, Order::count());
        $this->assertSame(1, Customer::count());

        $customer = Customer::first();
        $this->assertSame($this->restaurant->id, $customer->restaurant_id);
        $this->assertSame('11987654321', $customer->phone);
        $this->assertSame('Joao Silva Santos', $customer->name); // latest name wins

        // Both orders point at the customer and keep their own snapshot.
        $this->assertSame(2, Order::where('customer_id', $customer->id)->count());
        $this->assertSame(
            ['(11) 98765-4321', '11 98765 4321'],
            Order::orderBy('id')->pluck('customer_phone')->all()
        );

        // The admin Customers screen lists them with both orders.
        $this->actAsAdmin();

        $this->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee('Joao Silva Santos')
            ->assertSee('1 cliente(s) cadastrado(s)');

        $listed = Volt::test('customers.index')->get('customers')->getCollection();
        $this->assertCount(1, $listed);
        $this->assertSame(2, (int) $listed->first()->order_count);
        $this->assertSame(50.0, (float) $listed->first()->total_spent);
    }

    public function test_order_without_phone_does_not_create_a_customer(): void
    {
        $this->placePickupOrder('Maria', null);

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertNull($order->customer_id);
        $this->assertSame(0, Customer::count());
    }

    public function test_the_same_phone_is_a_separate_customer_in_each_restaurant(): void
    {
        $other = Restaurant::factory()->create();
        $resolver = app(ResolveCheckoutCustomer::class);

        $here = $resolver->handle($this->restaurant, 'Ana', '(11) 90000-1111');
        $there = $resolver->handle($other, 'Ana', '11900001111');
        $again = $resolver->handle($this->restaurant, 'Ana Paula', '+55 11 90000-1111');

        $this->assertNotSame($here->id, $there->id);
        $this->assertSame($here->id, $again->id);
        $this->assertSame('Ana Paula', $again->fresh()->name);
        $this->assertSame(2, Customer::count());

        $this->assertNull($resolver->handle($this->restaurant, 'Sem telefone', ' - '));
        $this->assertSame(2, Customer::count());
    }

    public function test_customers_screen_lists_only_the_panel_restaurant_customers(): void
    {
        $other = Restaurant::factory()->create();
        $panelId = Restaurant::query()->value('id');
        $foreign = $panelId === $this->restaurant->id ? $other : $this->restaurant;

        Customer::create(['restaurant_id' => $panelId, 'name' => 'Cliente Daqui', 'phone' => '11911112222']);
        Customer::create(['restaurant_id' => $foreign->id, 'name' => 'Cliente De Fora', 'phone' => '11933334444']);

        $this->actAsAdmin();

        $this->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee('Cliente Daqui')
            ->assertDontSee('Cliente De Fora');
    }
}
