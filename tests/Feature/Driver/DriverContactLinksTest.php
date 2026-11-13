<?php

namespace Tests\Feature\Driver;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Driver panel (EF-38): on the driver's own deliveries, one tap calls the
 * customer (tel:) or opens the delivery address in the maps app. Both links
 * come from the order's own data.
 */
class DriverContactLinksTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        // Lowest id: the restaurant the panel scopes to (CurrentRestaurant::id()).
        $this->restaurant = Restaurant::factory()->create();
    }

    private function driver(): User
    {
        return User::factory()->create(['role' => UserRole::Delivery]);
    }

    private function order(array $attributes = []): Order
    {
        return Order::factory()->for($this->restaurant)->create(array_merge([
            'status'                        => OrderStatus::OutForDelivery,
            'ready_at'                      => now(),
            'customer_phone'                => '(11) 98765-4321',
            'delivery_address_street'       => 'Rua das Flores',
            'delivery_address_number'       => '123',
            'delivery_address_complement'   => 'Apto 4',
            'delivery_address_neighborhood' => 'Centro',
            'delivery_address_city'         => 'São Paulo',
            'delivery_address_state'        => 'SP',
            'delivery_address_zip'          => '01001-000',
        ], $attributes));
    }

    public function test_links_use_the_order_phone_and_address(): void
    {
        $order = $this->order();

        $this->assertSame('tel:11987654321', $order->customerPhoneUri());
        $this->assertSame(
            'https://www.google.com/maps/search/?api=1&query='
                .rawurlencode('Rua das Flores, 123, Centro, São Paulo - SP, 01001-000'),
            $order->deliveryMapUrl(),
        );
    }

    public function test_phone_link_keeps_the_country_code_and_needs_a_phone(): void
    {
        $this->assertSame('tel:+5511987654321', $this->order(['customer_phone' => '+55 (11) 98765-4321'])->customerPhoneUri());
        $this->assertNull($this->order(['customer_phone' => null])->customerPhoneUri());
        $this->assertNull($this->order(['customer_phone' => ' - '])->customerPhoneUri());
    }

    public function test_map_link_needs_a_street(): void
    {
        $this->assertNull($this->order(['delivery_address_street' => null])->deliveryMapUrl());
        $this->assertSame(
            'https://www.google.com/maps/search/?api=1&query='.rawurlencode('Rua das Flores, Centro, SP'),
            $this->order([
                'delivery_address_number' => null,
                'delivery_address_city'   => null,
                'delivery_address_zip'    => null,
            ])->deliveryMapUrl(),
        );
    }

    public function test_own_deliveries_show_call_and_map_links(): void
    {
        $driver = $this->driver();
        $enRoute = $this->order(['driver_id' => $driver->id]);
        $pickup = $this->order([
            'driver_id'               => $driver->id,
            'status'                  => OrderStatus::ReadyForPickup,
            'customer_phone'          => '(21) 3333-4444',
            'delivery_address_street' => 'Avenida Brasil',
        ]);

        $this->actingAs($driver)
            ->get(route('admin.driver.index'))
            ->assertOk()
            ->assertDontSee('Undefined')
            ->assertSee('Ligar para o cliente')
            ->assertSee('Abrir no mapa')
            ->assertSeeHtml('href="tel:11987654321"')
            ->assertSeeHtml('href="'.e($enRoute->deliveryMapUrl()).'"')
            ->assertSeeHtml('href="tel:2133334444"')
            ->assertSeeHtml('href="'.e($pickup->deliveryMapUrl()).'"');
    }

    public function test_available_orders_have_no_contact_links(): void
    {
        $this->order(['status' => OrderStatus::ReadyForPickup, 'customer_name' => 'Maria Disponível']);

        $this->actingAs($this->driver())
            ->get(route('admin.driver.index'))
            ->assertOk()
            ->assertSee('Maria Disponível')
            ->assertDontSee('Ligar para o cliente')
            ->assertDontSee('Abrir no mapa')
            ->assertDontSeeHtml('href="tel:');
    }

    public function test_own_delivery_without_phone_shows_only_the_map_link(): void
    {
        $driver = $this->driver();
        $this->order(['driver_id' => $driver->id, 'customer_phone' => null]);

        $this->actingAs($driver)
            ->get(route('admin.driver.index'))
            ->assertOk()
            ->assertSee('Abrir no mapa')
            ->assertDontSee('Ligar para o cliente');
    }
}
