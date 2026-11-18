<?php

namespace Tests\Feature\Store;

use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Customer order tracking page (EF-16): steps follow the delivery type, every
 * reached step shows the time it happened, and a canceled order shows why.
 */
class OrderTrackingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Order whose append-only history walked through $path, as [status, 'HH:MM'] pairs.
     */
    private function orderThrough(array $path, DeliveryType $type = DeliveryType::Delivery, ?string $lastNote = null): Order
    {
        $day = Carbon::parse('2026-11-09');
        [$firstStatus, $firstTime] = $path[0];
        [$lastStatus] = $path[array_key_last($path)];

        $order = Order::factory()->create([
            'status'        => $lastStatus,
            'delivery_type' => $type,
            'created_at'    => $day->copy()->setTimeFromTimeString($firstTime),
        ]);

        $from = null;
        foreach ($path as $i => [$status, $time]) {
            OrderStatusHistory::create([
                'order_id'    => $order->id,
                'from_status' => $from?->value,
                'to_status'   => $status->value,
                'changed_by'  => null,
                'changed_at'  => $day->copy()->setTimeFromTimeString($time),
                'notes'       => $i === array_key_last($path) ? $lastNote : null,
            ]);
            $from = $status;
        }

        return $order;
    }

    private function track(Order $order)
    {
        return $this->get(route('store.order.tracking', $order->token))
            ->assertOk()
            ->assertDontSee('Undefined');
    }

    private function step(string $status, string $state): string
    {
        return 'data-step="'.$status.'" data-state="'.$state.'"';
    }

    public function test_out_for_delivery_shows_previous_steps_done_and_the_current_one_highlighted(): void
    {
        $order = $this->orderThrough([
            [OrderStatus::PendingConfirmation, '19:02'],
            [OrderStatus::Confirmed, '19:05'],
            [OrderStatus::InPreparation, '19:10'],
            [OrderStatus::ReadyForPickup, '19:30'],
            [OrderStatus::OutForDelivery, '19:35'],
        ]);

        $this->track($order)
            ->assertSee('Saiu para entrega')
            ->assertSee('Entregue')
            ->assertSeeHtml($this->step('pending_confirmation', 'done'))
            ->assertSeeHtml($this->step('confirmed', 'done'))
            ->assertSeeHtml($this->step('in_preparation', 'done'))
            ->assertSeeHtml($this->step('ready_for_pickup', 'done'))
            ->assertSeeHtml($this->step('out_for_delivery', 'current'))
            ->assertSeeHtml($this->step('delivered', 'pending'));
    }

    public function test_every_reached_step_shows_its_time(): void
    {
        $order = $this->orderThrough([
            [OrderStatus::PendingConfirmation, '19:02'],
            [OrderStatus::Confirmed, '19:05'],
            [OrderStatus::InPreparation, '19:10'],
            [OrderStatus::ReadyForPickup, '19:30'],
            [OrderStatus::OutForDelivery, '19:35'],
        ]);

        $this->track($order)
            ->assertSeeInOrder(['19:02', '19:05', '19:10', '19:30', '19:35']);
    }

    public function test_delivered_order_shows_every_delivery_step_done(): void
    {
        $order = $this->orderThrough([
            [OrderStatus::PendingConfirmation, '19:02'],
            [OrderStatus::Confirmed, '19:05'],
            [OrderStatus::InPreparation, '19:10'],
            [OrderStatus::ReadyForPickup, '19:30'],
            [OrderStatus::OutForDelivery, '19:35'],
            [OrderStatus::Delivered, '19:58'],
        ]);

        $this->track($order)
            ->assertSee('19:58')
            ->assertSeeHtml($this->step('out_for_delivery', 'done'))
            ->assertSeeHtml($this->step('delivered', 'done'))
            ->assertDontSeeHtml('data-state="current"');
    }

    public function test_pickup_order_ends_with_ready_for_pickup_and_picked_up(): void
    {
        $order = $this->orderThrough([
            [OrderStatus::PendingConfirmation, '12:00'],
            [OrderStatus::Confirmed, '12:01'],
            [OrderStatus::InPreparation, '12:03'],
            [OrderStatus::ReadyForPickup, '12:20'],
        ], DeliveryType::Pickup);

        $this->track($order)
            ->assertSee('Pronto para retirada')
            ->assertSee('Retirado')
            ->assertDontSee('Saiu para entrega')
            ->assertSeeHtml($this->step('ready_for_pickup', 'current'))
            ->assertSeeHtml($this->step('completed', 'pending'));
    }

    public function test_completed_pickup_order_keeps_the_timeline_with_every_step_done(): void
    {
        $order = $this->orderThrough([
            [OrderStatus::PendingConfirmation, '12:00'],
            [OrderStatus::Confirmed, '12:01'],
            [OrderStatus::InPreparation, '12:03'],
            [OrderStatus::ReadyForPickup, '12:20'],
            [OrderStatus::Completed, '12:26'],
        ], DeliveryType::Pickup);

        $this->track($order)
            ->assertSee('12:26')
            ->assertSeeHtml($this->step('completed', 'done'))
            ->assertDontSeeHtml('data-state="current"');
    }

    public function test_dine_in_order_ends_with_served(): void
    {
        $order = $this->orderThrough([
            [OrderStatus::PendingConfirmation, '20:00'],
            [OrderStatus::Confirmed, '20:01'],
            [OrderStatus::InPreparation, '20:02'],
        ], DeliveryType::DineIn);

        $this->track($order)
            ->assertSee('Servido')
            ->assertDontSee('Saiu para entrega')
            ->assertSeeHtml($this->step('in_preparation', 'current'));
    }

    public function test_just_placed_order_shows_the_received_time(): void
    {
        $order = $this->orderThrough([[OrderStatus::PendingConfirmation, '18:47']]);

        $this->track($order)
            ->assertSee('18:47')
            ->assertSeeHtml($this->step('pending_confirmation', 'current'))
            ->assertSeeHtml($this->step('confirmed', 'pending'));
    }

    public function test_canceled_order_shows_the_reason_and_time(): void
    {
        $order = $this->orderThrough([
            [OrderStatus::PendingConfirmation, '19:02'],
            [OrderStatus::Confirmed, '19:05'],
            [OrderStatus::Canceled, '19:20'],
        ], DeliveryType::Delivery, 'Acabou o pão do hambúrguer');

        $this->track($order)
            ->assertSee('Pedido cancelado')
            ->assertSee('Acabou o pão do hambúrguer')
            ->assertSee('19:20')
            ->assertDontSeeHtml('data-step=');
    }
}
