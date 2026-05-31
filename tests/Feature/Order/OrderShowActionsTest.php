<?php

namespace Tests\Feature\Order;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Volt\Volt;
use Tests\TestCase;

class OrderShowActionsTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => UserRole::Manager]);
    }

    public function test_show_route_renders_confirm_button_for_pending_order(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PendingConfirmation]);

        $this->actingAs($this->manager())
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Confirmar pedido')
            ->assertDontSee('Undefined');
    }

    public function test_manager_can_confirm_pending_order(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::PendingConfirmation,
            'confirmed_at' => null,
        ]);

        $this->actingAs($this->manager());

        Volt::test('orders.show', ['order' => $order])
            ->call('confirm')
            ->assertHasNoErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertNotNull($order->confirmed_at);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => OrderStatus::Confirmed->value,
        ]);
    }

    public function test_cancel_requires_a_reason(): void
    {
        $order = Order::factory()->confirmed()->create();

        $this->actingAs($this->manager());

        Volt::test('orders.show', ['order' => $order])
            ->set('cancelReason', '')
            ->call('cancel')
            ->assertHasErrors('cancelReason');

        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
    }

    public function test_manager_can_cancel_with_reason(): void
    {
        $order = Order::factory()->confirmed()->create();

        $this->actingAs($this->manager());

        Volt::test('orders.show', ['order' => $order])
            ->set('cancelReason', 'Cliente desistiu')
            ->call('cancel')
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::Canceled, $order->fresh()->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => OrderStatus::Canceled->value,
            'notes' => 'Cliente desistiu',
        ]);
    }

    public function test_kitchen_user_cannot_update_order_status(): void
    {
        $kitchen = User::factory()->create(['role' => UserRole::Kitchen]);
        $order = Order::factory()->create(['status' => OrderStatus::PendingConfirmation]);

        $this->assertTrue(Gate::forUser($kitchen)->denies('updateStatus', $order));

        $this->actingAs($kitchen);

        Volt::test('orders.show', ['order' => $order])
            ->call('confirm')
            ->assertForbidden();

        $this->assertSame(OrderStatus::PendingConfirmation, $order->fresh()->status);
    }

    public function test_manager_can_complete_a_delivered_order(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Delivered]);

        $this->actingAs($this->manager());

        Volt::test('orders.show', ['order' => $order])
            ->call('complete')
            ->assertHasNoErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertNotNull($order->completed_at);
    }

    public function test_manager_can_complete_a_ready_pickup_order(): void
    {
        $order = Order::factory()->pickup()->create(['status' => OrderStatus::ReadyForPickup]);

        $this->actingAs($this->manager());

        Volt::test('orders.show', ['order' => $order])
            ->call('complete')
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::Completed, $order->fresh()->status);
    }

    public function test_delivery_order_cannot_be_completed_from_ready(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::ReadyForPickup,
            'delivery_type' => DeliveryType::Delivery,
        ]);

        $this->actingAs($this->manager());

        Volt::test('orders.show', ['order' => $order])
            ->call('complete')
            ->assertForbidden();

        $this->assertSame(OrderStatus::ReadyForPickup, $order->fresh()->status);
    }

    public function test_rejecting_a_pending_order_cancels_it_with_reason(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::PendingConfirmation]);

        $this->actingAs($this->manager());

        Volt::test('orders.show', ['order' => $order])
            ->set('cancelReason', 'Fora do horário de atendimento')
            ->call('cancel')
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::Canceled, $order->fresh()->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => OrderStatus::PendingConfirmation->value,
            'to_status' => OrderStatus::Canceled->value,
            'notes' => 'Fora do horário de atendimento',
        ]);
    }
}
