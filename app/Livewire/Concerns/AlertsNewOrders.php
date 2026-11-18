<?php

namespace App\Livewire\Concerns;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\CurrentRestaurant;
use Illuminate\Support\Collection;

/**
 * New-order alert for a polling operational panel (counter, kitchen).
 *
 * The panel names the status that makes an order "new" for it. Every check
 * dispatches `order-alerts` with how many orders wait in that status (`count`)
 * and how many of them were not alerted yet (`fresh`); the browser component
 * x-orders.new-order-alert rings only when `fresh` > 0.
 *
 * The ids already alerted live in the session, per status and restaurant, so
 * the next poll, a reload or coming back to the screen never rings twice for
 * the same order. Ids, not "highest id seen": orders reach a status out of id
 * order (an older pending order can be confirmed after a newer one).
 */
trait AlertsNewOrders
{
    /** Orders waiting in the alert status, for the tab title (initial render). */
    public int $alertCount = 0;

    /** Status that makes an order "new" on this panel. */
    abstract protected function alertStatus(): OrderStatus;

    public function mountAlertsNewOrders(): void
    {
        $waiting = $this->waitingAlertIds();

        // First visit: orders already waiting are not news.
        if (! session()->has($this->alertSessionKey())) {
            session([$this->alertSessionKey() => $waiting->all()]);
        }

        $this->alertCount = $waiting->count();
    }

    public function checkNewOrders(): void
    {
        $waiting = $this->waitingAlertIds();
        $alerted = collect(session($this->alertSessionKey(), []));

        $fresh = $waiting->diff($alerted);

        // Keep only what still waits: the list never grows past the queue.
        session([$this->alertSessionKey() => $waiting->all()]);

        $this->alertCount = $waiting->count();
        $this->dispatch('order-alerts', count: $waiting->count(), fresh: $fresh->count());
    }

    /** @return Collection<int, int> */
    private function waitingAlertIds(): Collection
    {
        return Order::where('restaurant_id', CurrentRestaurant::id())
            ->byStatus($this->alertStatus())
            ->pluck('id');
    }

    private function alertSessionKey(): string
    {
        return 'order_alerts.'.$this->alertStatus()->value.'.'.CurrentRestaurant::id();
    }
}
