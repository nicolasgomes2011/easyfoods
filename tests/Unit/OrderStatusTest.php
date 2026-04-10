<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    // --- isFinal ---

    public function test_completed_is_final(): void
    {
        $this->assertTrue(OrderStatus::Completed->isFinal());
    }

    public function test_canceled_is_final(): void
    {
        $this->assertTrue(OrderStatus::Canceled->isFinal());
    }

    public function test_active_statuses_are_not_final(): void
    {
        $active = [
            OrderStatus::PendingConfirmation,
            OrderStatus::Confirmed,
            OrderStatus::InPreparation,
            OrderStatus::ReadyForPickup,
            OrderStatus::OutForDelivery,
            OrderStatus::Delivered,
        ];

        foreach ($active as $status) {
            $this->assertFalse($status->isFinal(), "{$status->value} should not be final");
        }
    }

    // --- isActive ---

    public function test_draft_is_not_active(): void
    {
        $this->assertFalse(OrderStatus::Draft->isActive());
    }

    public function test_final_statuses_are_not_active(): void
    {
        $this->assertFalse(OrderStatus::Completed->isActive());
        $this->assertFalse(OrderStatus::Canceled->isActive());
    }

    public function test_in_progress_statuses_are_active(): void
    {
        $active = [
            OrderStatus::PendingConfirmation,
            OrderStatus::Confirmed,
            OrderStatus::InPreparation,
            OrderStatus::ReadyForPickup,
            OrderStatus::OutForDelivery,
            OrderStatus::Delivered,
        ];

        foreach ($active as $status) {
            $this->assertTrue($status->isActive(), "{$status->value} should be active");
        }
    }

    // --- canTransitionTo ---

    public function test_draft_can_transition_to_pending_confirmation(): void
    {
        $this->assertTrue(OrderStatus::Draft->canTransitionTo(OrderStatus::PendingConfirmation));
    }

    public function test_draft_can_be_canceled(): void
    {
        $this->assertTrue(OrderStatus::Draft->canTransitionTo(OrderStatus::Canceled));
    }

    public function test_pending_confirmation_can_be_confirmed(): void
    {
        $this->assertTrue(OrderStatus::PendingConfirmation->canTransitionTo(OrderStatus::Confirmed));
    }

    public function test_pending_confirmation_can_be_canceled(): void
    {
        $this->assertTrue(OrderStatus::PendingConfirmation->canTransitionTo(OrderStatus::Canceled));
    }

    public function test_confirmed_can_go_to_in_preparation(): void
    {
        $this->assertTrue(OrderStatus::Confirmed->canTransitionTo(OrderStatus::InPreparation));
    }

    public function test_in_preparation_can_go_to_ready_for_pickup(): void
    {
        $this->assertTrue(OrderStatus::InPreparation->canTransitionTo(OrderStatus::ReadyForPickup));
    }

    public function test_ready_for_pickup_can_go_to_out_for_delivery(): void
    {
        $this->assertTrue(OrderStatus::ReadyForPickup->canTransitionTo(OrderStatus::OutForDelivery));
    }

    public function test_ready_for_pickup_can_go_directly_to_delivered(): void
    {
        $this->assertTrue(OrderStatus::ReadyForPickup->canTransitionTo(OrderStatus::Delivered));
    }

    public function test_out_for_delivery_can_go_to_delivered(): void
    {
        $this->assertTrue(OrderStatus::OutForDelivery->canTransitionTo(OrderStatus::Delivered));
    }

    public function test_delivered_can_go_to_completed(): void
    {
        $this->assertTrue(OrderStatus::Delivered->canTransitionTo(OrderStatus::Completed));
    }

    public function test_completed_cannot_transition_anywhere(): void
    {
        foreach (OrderStatus::cases() as $status) {
            $this->assertFalse(OrderStatus::Completed->canTransitionTo($status));
        }
    }

    public function test_canceled_cannot_transition_anywhere(): void
    {
        foreach (OrderStatus::cases() as $status) {
            $this->assertFalse(OrderStatus::Canceled->canTransitionTo($status));
        }
    }

    public function test_cannot_skip_from_draft_to_in_preparation(): void
    {
        $this->assertFalse(OrderStatus::Draft->canTransitionTo(OrderStatus::InPreparation));
    }

    public function test_cannot_go_backwards_from_confirmed_to_draft(): void
    {
        $this->assertFalse(OrderStatus::Confirmed->canTransitionTo(OrderStatus::Draft));
    }

    // --- all non-final statuses can be canceled ---

    public function test_all_active_statuses_can_be_canceled(): void
    {
        $cancelable = [
            OrderStatus::Draft,
            OrderStatus::PendingConfirmation,
            OrderStatus::Confirmed,
            OrderStatus::InPreparation,
            OrderStatus::ReadyForPickup,
            OrderStatus::OutForDelivery,
        ];

        foreach ($cancelable as $status) {
            $this->assertTrue(
                $status->canTransitionTo(OrderStatus::Canceled),
                "{$status->value} should be cancelable"
            );
        }
    }

    public function test_delivered_cannot_be_canceled(): void
    {
        $this->assertFalse(OrderStatus::Delivered->canTransitionTo(OrderStatus::Canceled));
    }
}
