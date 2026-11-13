<?php

namespace App\Support;

use App\Enums\TimeBand;
use App\Models\Order;
use App\Models\PrepSample;
use App\Models\Product;
use Carbon\CarbonInterface;

/**
 * Learned prep time of an order (docs/scope/eta-intelligence.md):
 *
 *   slowest product × kitchen load factor (now)
 *
 * where each product's time is the weighted moving average of its real prep
 * times (prep_samples) in the same time band over the last 30 days, recent
 * samples weighing more. Until a product has MIN_SAMPLES of them, its base
 * prep time (products.prep_minutes) is used instead.
 *
 * Samples are normalized by the load they were made under, so a burger that
 * took 24 minutes with a full kitchen (×1.2) counts as 20 at normal load and
 * the current load is applied once, on top. The time band factor is learned:
 * only samples from the band of the estimate are averaged.
 */
final class LearnedPrepTime
{
    /** Samples per product (and band) before the history replaces the base time. */
    public const MIN_SAMPLES = 7;

    public const WINDOW_DAYS = 30;

    /** A sample this many days old weighs half of one from today. */
    public const HALF_LIFE_DAYS = 7;

    /** An order started longer ago than this no longer counts as kitchen load (forgotten on screen). */
    public const LOAD_LOOKBACK_HOURS = 4;

    /** Estimated minutes for the order, at least 1. */
    public static function minutes(Order $order, ?CarbonInterface $at = null): int
    {
        $at ??= now();

        $productIds = $order->items()->whereNotNull('product_id')->distinct()->pluck('product_id');
        $baseMinutes = Product::whereIn('id', $productIds)->pluck('prep_minutes', 'id');

        $samples = $baseMinutes->isEmpty() ? collect() : PrepSample::query()
            ->where('restaurant_id', $order->restaurant_id)
            ->whereIn('product_id', $baseMinutes->keys())
            ->where('time_band', TimeBand::at($at)->value)
            ->where('ready_at', '>=', $at->copy()->subDays(self::WINDOW_DAYS))
            ->where('ready_at', '<=', $at)
            ->get(['product_id', 'prep_seconds', 'kitchen_load', 'ready_at'])
            ->groupBy('product_id');

        $slowest = $baseMinutes
            ->map(fn ($base, $productId) => self::productMinutes(
                (int) ($base ?? ReadyEstimate::DEFAULT_PREP_MINUTES),
                $samples->get($productId, collect()),
                $at,
            ))
            ->max() ?? ReadyEstimate::DEFAULT_PREP_MINUTES;

        $load = self::kitchenLoad($order->restaurant_id, $at, $order->id);

        return max(1, (int) round($slowest * self::loadFactor($load)));
    }

    /** Weighted average of the samples (load-normalized), or the base time with too few of them. */
    private static function productMinutes(int $baseMinutes, $samples, CarbonInterface $at): float
    {
        if ($samples->count() < self::MIN_SAMPLES) {
            return $baseMinutes;
        }

        $weightedSum = 0.0;
        $weights = 0.0;

        foreach ($samples as $sample) {
            $ageDays = max(0, $at->getTimestamp() - $sample->ready_at->getTimestamp()) / 86400;
            $weight = 0.5 ** ($ageDays / self::HALF_LIFE_DAYS);

            $weightedSum += $weight * ($sample->prep_seconds / 60) / self::loadFactor($sample->kitchen_load);
            $weights += $weight;
        }

        return $weightedSum / $weights;
    }

    /** Kitchen load table from docs/scope/eta-intelligence.md. */
    public static function loadFactor(int $ordersInPreparation): float
    {
        return match (true) {
            $ordersInPreparation >= 10 => 1.8,
            $ordersInPreparation >= 6  => 1.5,
            $ordersInPreparation >= 3  => 1.2,
            default                    => 1.0,
        };
    }

    /**
     * Orders of the restaurant that were in preparation at that moment (started
     * in the last LOAD_LOOKBACK_HOURS, not yet ready nor canceled), besides the
     * given one. Read from the milestones, so it also works for a past moment.
     */
    public static function kitchenLoad(int $restaurantId, CarbonInterface $at, ?int $exceptOrderId = null): int
    {
        return Order::query()
            ->where('restaurant_id', $restaurantId)
            ->when($exceptOrderId !== null, fn ($query) => $query->whereKeyNot($exceptOrderId))
            ->where('preparation_started_at', '>=', $at->copy()->subHours(self::LOAD_LOOKBACK_HOURS))
            ->where('preparation_started_at', '<=', $at)
            ->where(fn ($query) => $query->whereNull('ready_at')->orWhere('ready_at', '>', $at))
            ->where(fn ($query) => $query->whereNull('canceled_at')->orWhere('canceled_at', '>', $at))
            ->count();
    }
}
