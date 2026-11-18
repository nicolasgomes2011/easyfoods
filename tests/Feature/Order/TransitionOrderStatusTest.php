<?php

namespace Tests\Feature\Order;

use App\Actions\Orders\TransitionOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

class TransitionOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    private function action(): TransitionOrderStatus
    {
        return app(TransitionOrderStatus::class);
    }

    private function actor(): User
    {
        return User::factory()->create(['role' => UserRole::Manager]);
    }

    public function test_confirm_sets_status_milestone_and_history(): void
    {
        $order = Order::factory()->create([
            'status' => OrderStatus::PendingConfirmation,
            'confirmed_at' => null,
        ]);
        $actor = $this->actor();

        $this->action()->execute($order, OrderStatus::Confirmed, $actor);

        $order->refresh();
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertNotNull($order->confirmed_at);

        $history = OrderStatusHistory::where('order_id', $order->id)->latest('id')->first();
        $this->assertNotNull($history);
        $this->assertSame(OrderStatus::PendingConfirmation, $history->from_status);
        $this->assertSame(OrderStatus::Confirmed, $history->to_status);
        $this->assertSame($actor->id, $history->changed_by);
        $this->assertNull($history->notes);
    }

    public function test_cancel_records_reason_and_milestone(): void
    {
        $order = Order::factory()->confirmed()->create(['canceled_at' => null]);

        $this->action()->execute($order, OrderStatus::Canceled, $this->actor(), 'Produto em falta');

        $order->refresh();
        $this->assertSame(OrderStatus::Canceled, $order->status);
        $this->assertNotNull($order->canceled_at);

        $history = OrderStatusHistory::where('order_id', $order->id)->latest('id')->first();
        $this->assertSame('Produto em falta', $history->notes);
        $this->assertSame(OrderStatus::Canceled, $history->to_status);
    }

    public function test_cancel_without_reason_is_rejected(): void
    {
        $order = Order::factory()->confirmed()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->action()->execute($order, OrderStatus::Canceled, $this->actor(), '   ');
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $order = Order::factory()->completed()->create();

        $this->expectException(ValidationException::class);

        $this->action()->execute($order, OrderStatus::InPreparation, $this->actor());
    }

    public function test_same_status_is_an_idempotent_noop(): void
    {
        $order = Order::factory()->confirmed()->create();

        $this->action()->execute($order, OrderStatus::Confirmed, $this->actor());

        $this->assertSame(0, OrderStatusHistory::where('order_id', $order->id)->count());
    }

    public function test_start_of_preparation_and_departure_are_recorded_as_milestones(): void
    {
        $order = Order::factory()->confirmed()->create();
        $actor = $this->actor();

        $this->travelTo($prepStart = now()->startOfMinute());
        $this->action()->execute($order, OrderStatus::InPreparation, $actor);

        $this->travelTo(now()->addMinutes(12));
        $this->action()->execute($order, OrderStatus::ReadyForPickup, $actor);

        $this->travelTo($departure = now()->addMinutes(3));
        $this->action()->execute($order, OrderStatus::OutForDelivery, $actor);

        $order->refresh();
        $this->assertTrue($order->preparation_started_at->equalTo($prepStart));
        $this->assertTrue($order->out_for_delivery_at->equalTo($departure));
    }

    public function test_preparation_and_departure_milestones_are_set_only_once(): void
    {
        // Already filled (e.g. by the backfill): a later transition must not overwrite it.
        $earlier = now()->subHour()->startOfMinute();
        $actor = $this->actor();

        $preparing = Order::factory()->confirmed()->create(['preparation_started_at' => $earlier]);
        $this->action()->execute($preparing, OrderStatus::InPreparation, $actor);

        $leaving = Order::factory()->create([
            'status'              => OrderStatus::ReadyForPickup,
            'out_for_delivery_at' => $earlier,
        ]);
        $this->action()->execute($leaving, OrderStatus::OutForDelivery, $actor);

        $this->assertTrue($preparing->fresh()->preparation_started_at->equalTo($earlier));
        $this->assertTrue($leaving->fresh()->out_for_delivery_at->equalTo($earlier));
    }
}
