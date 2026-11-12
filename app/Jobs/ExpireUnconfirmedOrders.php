<?php

namespace App\Jobs;

use App\Actions\Orders\TransitionOrderStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cancels orders nobody confirmed within their restaurant's window
 * (restaurants.confirmation_timeout_minutes; 0 = never), through
 * TransitionOrderStatus with a system actor and a reason the customer sees.
 *
 * Scheduled every minute in routes/console.php. Not queued on purpose: it runs
 * inside the scheduler process, so no queue worker is needed.
 */
class ExpireUnconfirmedOrders
{
    use Dispatchable;

    public const REASON = 'Não confirmado a tempo';

    public function handle(TransitionOrderStatus $transition): void
    {
        $restaurants = Restaurant::query()
            ->where('confirmation_timeout_minutes', '>', 0)
            ->get(['id', 'confirmation_timeout_minutes']);

        foreach ($restaurants as $restaurant) {
            // Deadline computed in PHP: date arithmetic in SQL differs between SQLite and MySQL.
            $deadline = now()->subMinutes($restaurant->confirmation_timeout_minutes);

            // Orders are created already in pending_confirmation, so created_at is when the wait began.
            $expired = Order::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('status', OrderStatus::PendingConfirmation->value)
                ->where('created_at', '<=', $deadline)
                ->pluck('id');

            foreach ($expired as $orderId) {
                try {
                    $this->expire($transition, $orderId);
                } catch (Throwable $e) {
                    // One bad order must not keep the others waiting forever.
                    report($e);
                }
            }
        }
    }

    private function expire(TransitionOrderStatus $transition, int $orderId): void
    {
        DB::transaction(function () use ($transition, $orderId) {
            // Re-check under the lock: staff may have confirmed it since the query above,
            // and Confirmed → Canceled is a valid transition we must not make here.
            $order = Order::query()->lockForUpdate()->find($orderId);

            if ($order?->status !== OrderStatus::PendingConfirmation) {
                return;
            }

            $transition->execute($order, OrderStatus::Canceled, null, self::REASON);
        });
    }
}
