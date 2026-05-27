<?php

namespace Tests\Feature\Order;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Volt;
use Tests\TestCase;

class KitchenActionsTest extends TestCase
{
    use RefreshDatabase;

    private function kitchen(): User
    {
        return User::factory()->create(['role' => UserRole::Kitchen]);
    }

    public function test_kitchen_route_renders_start_button_for_confirmed_order(): void
    {
        Order::factory()->confirmed()->create();

        $this->actingAs($this->kitchen())
            ->get(route('admin.kitchen.index'))
            ->assertOk()
            ->assertSee('Iniciar preparo')
            ->assertDontSee('Undefined');
    }

    public function test_kitchen_can_start_preparing(): void
    {
        $order = Order::factory()->confirmed()->create();

        $this->actingAs($this->kitchen());

        Volt::test('kitchen.index')
            ->call('startPreparing', $order->id)
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::InPreparation, $order->fresh()->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => OrderStatus::Confirmed->value,
            'to_status' => OrderStatus::InPreparation->value,
        ]);
    }

    public function test_kitchen_can_mark_ready_and_sets_ready_at(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::InPreparation,
            'ready_at' => null,
        ]);

        $this->actingAs($this->kitchen());

        Volt::test('kitchen.index')
            ->call('markReady', $order->id)
            ->assertHasNoErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::ReadyForPickup, $order->status);
        $this->assertNotNull($order->ready_at);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => OrderStatus::ReadyForPickup->value,
        ]);
    }

    public function test_delivery_user_cannot_transition_in_kitchen(): void
    {
        $delivery = User::factory()->create(['role' => UserRole::Delivery]);
        $order = Order::factory()->confirmed()->create();

        $this->assertTrue(Gate::forUser($delivery)->denies('transitionInKitchen', $order));

        $this->actingAs($delivery);

        Volt::test('kitchen.index')
            ->call('startPreparing', $order->id)
            ->assertForbidden();

        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
    }
}
