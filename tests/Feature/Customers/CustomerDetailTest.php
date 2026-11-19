<?php

namespace Tests\Feature\Customers;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\CurrentRestaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * EF-23: restaurant view of one customer — contact, addresses, number of
 * orders, average ticket, last order, most ordered items and the paginated
 * list of their orders. Fenced to the restaurant like the customers list.
 */
class CustomerDetailTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private Restaurant $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $this->other      = Restaurant::factory()->create();

        $this->actingAs(User::factory()->create([
            'role'          => UserRole::Manager,
            'restaurant_id' => $this->restaurant->id,
        ]));
    }

    private function customerOf(Restaurant $restaurant, array $attributes = []): Customer
    {
        return Customer::factory()->create(['restaurant_id' => $restaurant->id] + $attributes);
    }

    private function orderOf(Restaurant $restaurant, Customer $customer, array $attributes = []): Order
    {
        return CurrentRestaurant::runAs($restaurant->id, fn () => Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'customer_id'   => $customer->id,
        ] + $attributes));
    }

    private function item(Order $order, string $name, int $quantity, float $price = 10): void
    {
        OrderItem::create([
            'order_id'     => $order->id,
            'product_name' => $name,
            'unit_price'   => $price,
            'quantity'     => $quantity,
            'subtotal'     => $price * $quantity,
        ]);
    }

    public function test_clicking_a_customer_on_the_list_opens_the_detail(): void
    {
        $customer = $this->customerOf($this->restaurant, ['name' => 'Joana Lima']);
        $this->orderOf($this->restaurant, $customer);

        $this->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee(route('admin.customers.show', $customer), false);
    }

    public function test_the_detail_shows_contact_addresses_numbers_top_items_and_orders(): void
    {
        $customer = $this->customerOf($this->restaurant, [
            'name'  => 'Joana Lima',
            'email' => 'joana@example.com',
            'phone' => '(11) 98888-7777',
        ]);
        $customer->addresses()->create([
            'label' => 'Casa', 'street' => 'Rua das Flores', 'number' => '42',
            'neighborhood' => 'Centro', 'city' => 'Campinas', 'state' => 'SP', 'zip' => '13000-000',
            'is_default' => true,
        ]);

        $first = $this->orderOf($this->restaurant, $customer, [
            'status' => OrderStatus::Completed, 'total' => 40, 'created_at' => Carbon::parse('2026-11-10 12:00'),
        ]);
        $this->item($first, 'Pizza Margherita', 2);
        $this->item($first, 'Refrigerante', 1);

        $second = $this->orderOf($this->restaurant, $customer, [
            'status' => OrderStatus::Delivered, 'total' => 60, 'created_at' => Carbon::parse('2026-11-15 20:30'),
        ]);
        $this->item($second, 'Pizza Margherita', 1);
        $this->item($second, 'Suco de Laranja', 3);

        // A canceled order counts in "pedidos" but not in the ticket nor in the top items.
        $canceled = $this->orderOf($this->restaurant, $customer, [
            'status' => OrderStatus::Canceled, 'total' => 500, 'created_at' => Carbon::parse('2026-11-12 19:00'),
        ]);
        $this->item($canceled, 'Lagosta', 9);

        $this->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertDontSee('Undefined')
            ->assertSee('Joana Lima')
            ->assertSee('joana@example.com')
            ->assertSee('(11) 98888-7777')
            ->assertSee('Rua das Flores, 42')
            ->assertSee('R$ 50,00')            // average ticket: (40 + 60) / 2
            ->assertSee('R$ 100,00')           // total spent
            ->assertSee('15/11/2026 20:30')    // last order
            ->assertSeeInOrder(['Pizza Margherita', 'Suco de Laranja', 'Refrigerante'])
            ->assertDontSee('Lagosta')
            ->assertSee('#' . $first->number)
            ->assertSee('#' . $canceled->number)
            ->assertSee(route('admin.orders.show', $second), false);

        $component = Volt::test('customers.show', ['customer' => $customer]);
        $stats = $component->instance()->stats;

        $this->assertSame(3, $stats['order_count']);
        $this->assertEquals(50, $stats['average_ticket']);
        $this->assertEquals(100, $stats['total_spent']);
        $this->assertSame([
            ['product_name' => 'Pizza Margherita', 'quantity' => 3],
            ['product_name' => 'Suco de Laranja', 'quantity' => 3],
            ['product_name' => 'Refrigerante', 'quantity' => 1],
        ], $component->instance()->topItems);
    }

    public function test_a_customer_without_orders_shows_empty_states(): void
    {
        $customer = $this->customerOf($this->restaurant, ['name' => 'Sem Pedidos']);

        $this->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee('Sem Pedidos')
            ->assertSee('Nenhum pedido deste cliente ainda.')
            ->assertSee('Nenhum endereço cadastrado.')
            ->assertDontSee('Undefined');
    }

    public function test_orders_are_paginated_newest_first(): void
    {
        $customer = $this->customerOf($this->restaurant);

        foreach (range(1, 12) as $day) {
            $this->orderOf($this->restaurant, $customer, [
                'status' => OrderStatus::Completed, 'created_at' => Carbon::parse("2026-11-{$day} 12:00"),
            ]);
        }

        $orders = Volt::test('customers.show', ['customer' => $customer])->instance()->orders;

        $this->assertSame(12, $orders->total());
        $this->assertCount(10, $orders->items());
        $this->assertSame('2026-11-12', $orders->first()->created_at->toDateString());
    }

    public function test_a_customer_of_another_restaurant_is_a_404(): void
    {
        $customer = $this->customerOf($this->other, ['name' => 'Cliente Beta']);
        $this->orderOf($this->other, $customer);

        $this->get(route('admin.customers.show', $customer))->assertNotFound();
    }

    public function test_a_legacy_customer_without_restaurant_is_seen_only_where_they_ordered(): void
    {
        $legacy = Customer::factory()->create(['name' => 'Cliente Antigo']);
        $ownOrder   = $this->orderOf($this->restaurant, $legacy, ['status' => OrderStatus::Completed, 'total' => 30]);
        $otherOrder = $this->orderOf($this->other, $legacy, ['status' => OrderStatus::Completed, 'total' => 70]);

        $this->get(route('admin.customers.show', $legacy))
            ->assertOk()
            ->assertSee('#' . $ownOrder->number)
            ->assertDontSee('#' . $otherOrder->number)
            ->assertSee('R$ 30,00')
            ->assertDontSee('R$ 70,00');

        $onlyOther = Customer::factory()->create();
        $this->orderOf($this->other, $onlyOther);

        $this->get(route('admin.customers.show', $onlyOther))->assertNotFound();
    }

    public function test_kitchen_and_delivery_cannot_open_the_detail(): void
    {
        $customer = $this->customerOf($this->restaurant);

        foreach ([UserRole::Kitchen, UserRole::Delivery] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'restaurant_id' => $this->restaurant->id]));

            $this->get(route('admin.customers.show', $customer))->assertForbidden();
        }
    }
}
