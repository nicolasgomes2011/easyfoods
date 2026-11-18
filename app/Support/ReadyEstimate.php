<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\CarbonInterface;

/**
 * Ready-time estimate of an order, counted from the confirmation. The prep
 * time comes from LearnedPrepTime: the slowest item's learned time (or its
 * base prep_minutes while it has little history) × the kitchen load factor.
 *
 * Customer-facing rules (docs/scope/customer-side.md): the time shown never
 * goes backward, and an overdue estimate is pushed forward at most twice.
 */
final class ReadyEstimate
{
    public const MAX_ADJUSTMENTS = 2;

    /** How far an overdue estimate is pushed each time. */
    public const ADJUSTMENT_MINUTES = 5;

    public const DEFAULT_PREP_MINUTES = 15;

    /**
     * Prep time of the order: its slowest product, learned from the history
     * (default base time when no product is left), × the kitchen load factor.
     */
    public static function prepMinutes(Order $order, ?CarbonInterface $at = null): int
    {
        return LearnedPrepTime::minutes($order, $at);
    }

    /** First estimate, set on confirmation. An estimate already shown is never replaced. */
    public static function start(Order $order, ?CarbonInterface $from = null): void
    {
        if ($order->estimated_ready_at !== null) {
            return;
        }

        $from = ($from ?? now())->copy();
        $order->estimated_ready_at = $from->copy()->addMinutes(self::prepMinutes($order, $from));
    }

    /**
     * Pushes an overdue estimate forward while the kitchen still works on the
     * order, at most MAX_ADJUSTMENTS times and never backward. The update is
     * conditional, so two viewers polling at once cannot push it twice.
     * Returns whether the estimate moved.
     */
    public static function adjust(Order $order, ?CarbonInterface $now = null): bool
    {
        $now ??= now();
        $current = $order->estimated_ready_at;

        if ($current === null
            || ! in_array($order->status, [OrderStatus::Confirmed, OrderStatus::InPreparation], true)
            || $order->eta_adjustments >= self::MAX_ADJUSTMENTS
            || $now->lt($current)) {
            return false;
        }

        $next = $now->copy()->addMinutes(self::ADJUSTMENT_MINUTES)->startOfMinute();
        if ($next->lte($current)) {
            return false;
        }

        $moved = Order::whereKey($order->id)
            ->where('eta_adjustments', $order->eta_adjustments)
            ->where('estimated_ready_at', $current)
            ->update([
                'estimated_ready_at' => $next,
                'eta_adjustments'    => $order->eta_adjustments + 1,
            ]);

        if ($moved === 0) {
            $order->refresh(); // someone else moved it: show their value

            return false;
        }

        $order->forceFill([
            'estimated_ready_at' => $next,
            'eta_adjustments'    => $order->eta_adjustments + 1,
        ])->syncOriginal();

        return true;
    }
}
