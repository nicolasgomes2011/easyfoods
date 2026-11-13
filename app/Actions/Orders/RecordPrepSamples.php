<?php

namespace App\Actions\Orders;

use App\Enums\TimeBand;
use App\Models\Order;
use App\Models\PrepSample;
use App\Support\LearnedPrepTime;

/**
 * Writes the prep history of an order that just became ready: one sample per
 * product, with the real duration (preparation_started_at → ready_at) and the
 * kitchen load, time band and day of week of the start. Called by
 * TransitionOrderStatus inside its transaction; runs once per order.
 */
class RecordPrepSamples
{
    /** Longer than this the order was forgotten on screen, not prepared: no sample. */
    public const MAX_MINUTES = 240;

    public function execute(Order $order): void
    {
        $startedAt = $order->preparation_started_at;
        $readyAt = $order->ready_at;

        if ($startedAt === null || $readyAt === null) {
            return;
        }

        $seconds = $readyAt->getTimestamp() - $startedAt->getTimestamp();
        if ($seconds <= 0 || $seconds > self::MAX_MINUTES * 60) {
            return;
        }

        if (PrepSample::where('order_id', $order->id)->exists()) {
            return;
        }

        $quantities = $order->items()
            ->whereNotNull('product_id')
            ->whereHas('product')
            ->selectRaw('product_id, SUM(quantity) as quantity')
            ->groupBy('product_id')
            ->pluck('quantity', 'product_id');

        if ($quantities->isEmpty()) {
            return;
        }

        $load = LearnedPrepTime::kitchenLoad($order->restaurant_id, $startedAt, $order->id);
        $band = TimeBand::at($startedAt);

        foreach ($quantities as $productId => $quantity) {
            PrepSample::create([
                'restaurant_id' => $order->restaurant_id,
                'product_id'    => $productId,
                'order_id'      => $order->id,
                'quantity'      => (int) $quantity,
                'prep_seconds'  => $seconds,
                'kitchen_load'  => $load,
                'time_band'     => $band,
                'day_of_week'   => $startedAt->dayOfWeek,
                'started_at'    => $startedAt,
                'ready_at'      => $readyAt,
            ]);
        }
    }
}
