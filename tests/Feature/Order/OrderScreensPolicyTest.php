<?php

namespace Tests\Feature\Order;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The order screens call the OrderPolicy themselves (EF-63): the component
 * refuses a role that cannot manage orders even when it is mounted without
 * the route (and its role middleware) in front of it.
 */
class OrderScreensPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
    }

    private function actAs(UserRole $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role, 'restaurant_id' => $this->restaurant->id]));
    }

    public static function listScreens(): array
    {
        return [
            'index'       => ['orders.index'],
            'in-progress' => ['orders.in-progress'],
            'history'     => ['orders.history'],
        ];
    }

    #[DataProvider('listScreens')]
    public function test_list_screens_refuse_roles_that_do_not_manage_orders(string $screen): void
    {
        foreach ([UserRole::Kitchen, UserRole::Delivery] as $role) {
            $this->actAs($role);

            Volt::test($screen)->assertForbidden();
        }
    }

    #[DataProvider('listScreens')]
    public function test_list_screens_open_for_front_of_house_roles(string $screen): void
    {
        foreach ([UserRole::Admin, UserRole::Manager, UserRole::Attendant] as $role) {
            $this->actAs($role);

            Volt::test($screen)->assertOk();
        }
    }

    public function test_order_detail_refuses_roles_that_do_not_manage_orders(): void
    {
        $order = Order::factory()->create(['restaurant_id' => $this->restaurant->id]);

        foreach ([UserRole::Kitchen, UserRole::Delivery] as $role) {
            $this->actAs($role);

            Volt::test('orders.show', ['order' => $order])->assertForbidden();
        }
    }

    public function test_order_detail_opens_for_front_of_house_roles(): void
    {
        $order = Order::factory()->create(['restaurant_id' => $this->restaurant->id]);

        foreach ([UserRole::Admin, UserRole::Manager, UserRole::Attendant] as $role) {
            $this->actAs($role);

            Volt::test('orders.show', ['order' => $order])->assertOk()->assertSee('#'.$order->number);
        }
    }

    public function test_order_of_another_restaurant_is_still_not_found(): void
    {
        $other = Restaurant::factory()->create();
        $order = Order::factory()->create(['restaurant_id' => $other->id]);
        $this->actAs(UserRole::Admin);

        Volt::test('orders.show', ['order' => $order])->assertNotFound();
    }
}
