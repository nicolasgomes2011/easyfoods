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
}
