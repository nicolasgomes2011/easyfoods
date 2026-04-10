<?php

namespace Tests\Unit;

use App\Enums\PaymentStatus;
use PHPUnit\Framework\TestCase;

class PaymentStatusTest extends TestCase
{
    // --- isFinal ---

    public function test_paid_is_final(): void
    {
        $this->assertTrue(PaymentStatus::Paid->isFinal());
    }

    public function test_refunded_is_final(): void
    {
        $this->assertTrue(PaymentStatus::Refunded->isFinal());
    }

    public function test_canceled_is_final(): void
    {
        $this->assertTrue(PaymentStatus::Canceled->isFinal());
    }

    public function test_pending_is_not_final(): void
    {
        $this->assertFalse(PaymentStatus::Pending->isFinal());
    }

    public function test_authorized_is_not_final(): void
    {
        $this->assertFalse(PaymentStatus::Authorized->isFinal());
    }

    public function test_failed_is_not_final(): void
    {
        $this->assertFalse(PaymentStatus::Failed->isFinal());
    }

    public function test_partially_refunded_is_not_final(): void
    {
        $this->assertFalse(PaymentStatus::PartiallyRefunded->isFinal());
    }

    // --- isPaid ---

    public function test_paid_status_is_paid(): void
    {
        $this->assertTrue(PaymentStatus::Paid->isPaid());
    }

    public function test_other_statuses_are_not_paid(): void
    {
        $notPaid = [
            PaymentStatus::Pending,
            PaymentStatus::Authorized,
            PaymentStatus::Failed,
            PaymentStatus::Refunded,
            PaymentStatus::PartiallyRefunded,
            PaymentStatus::Canceled,
        ];

        foreach ($notPaid as $status) {
            $this->assertFalse($status->isPaid(), "{$status->value} should not be isPaid()");
        }
    }

    // --- canTransitionTo ---

    public function test_pending_can_go_to_authorized(): void
    {
        $this->assertTrue(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Authorized));
    }

    public function test_pending_can_go_directly_to_paid(): void
    {
        $this->assertTrue(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Paid));
    }

    public function test_pending_can_fail(): void
    {
        $this->assertTrue(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Failed));
    }

    public function test_pending_can_be_canceled(): void
    {
        $this->assertTrue(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Canceled));
    }

    public function test_authorized_can_be_captured_to_paid(): void
    {
        $this->assertTrue(PaymentStatus::Authorized->canTransitionTo(PaymentStatus::Paid));
    }

    public function test_authorized_can_fail(): void
    {
        $this->assertTrue(PaymentStatus::Authorized->canTransitionTo(PaymentStatus::Failed));
    }

    public function test_authorized_can_be_canceled(): void
    {
        $this->assertTrue(PaymentStatus::Authorized->canTransitionTo(PaymentStatus::Canceled));
    }

    public function test_paid_can_be_refunded(): void
    {
        $this->assertTrue(PaymentStatus::Paid->canTransitionTo(PaymentStatus::Refunded));
    }

    public function test_paid_can_be_partially_refunded(): void
    {
        $this->assertTrue(PaymentStatus::Paid->canTransitionTo(PaymentStatus::PartiallyRefunded));
    }

    public function test_partially_refunded_can_be_fully_refunded(): void
    {
        $this->assertTrue(PaymentStatus::PartiallyRefunded->canTransitionTo(PaymentStatus::Refunded));
    }

    public function test_failed_can_retry_to_pending(): void
    {
        $this->assertTrue(PaymentStatus::Failed->canTransitionTo(PaymentStatus::Pending));
    }

    public function test_refunded_has_no_transitions(): void
    {
        foreach (PaymentStatus::cases() as $status) {
            $this->assertFalse(PaymentStatus::Refunded->canTransitionTo($status));
        }
    }

    public function test_canceled_has_no_transitions(): void
    {
        foreach (PaymentStatus::cases() as $status) {
            $this->assertFalse(PaymentStatus::Canceled->canTransitionTo($status));
        }
    }

    public function test_cannot_go_from_paid_to_pending(): void
    {
        $this->assertFalse(PaymentStatus::Paid->canTransitionTo(PaymentStatus::Pending));
    }

    public function test_cannot_go_from_authorized_to_pending(): void
    {
        $this->assertFalse(PaymentStatus::Authorized->canTransitionTo(PaymentStatus::Pending));
    }
}
