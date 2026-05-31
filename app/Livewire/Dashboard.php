<?php

namespace App\Livewire;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Dashboard extends Component
{
    private function rid(): ?int
    {
        return Restaurant::query()->value('id');
    }

    #[Computed]
    public function openCount(): int
    {
        return Order::byStatus(OrderStatus::PendingConfirmation)
            ->where('restaurant_id', $this->rid())->count();
    }

    #[Computed]
    public function inPreparationCount(): int
    {
        return Order::byStatus(OrderStatus::InPreparation)
            ->where('restaurant_id', $this->rid())->count();
    }

    #[Computed]
    public function readyCount(): int
    {
        return Order::byStatus(OrderStatus::ReadyForPickup)
            ->where('restaurant_id', $this->rid())->count();
    }

    #[Computed]
    public function todayOrderCount(): int
    {
        return Order::where('restaurant_id', $this->rid())
            ->whereDate('created_at', today())
            ->whereNotIn('status', [OrderStatus::Canceled->value, OrderStatus::Draft->value])
            ->count();
    }

    #[Computed]
    public function avgPrepMinutes(): ?int
    {
        $orders = Order::where('restaurant_id', $this->rid())
            ->whereNotNull('ready_at')
            ->whereDate('ready_at', today())
            ->get(['created_at', 'ready_at']);

        if ($orders->isEmpty()) return null;

        return (int) round($orders->avg(
            fn ($o) => $o->created_at->diffInMinutes($o->ready_at)
        ));
    }

    #[Computed]
    public function todayRevenue(): float
    {
        return (float) Order::where('restaurant_id', $this->rid())
            ->whereIn('status', [OrderStatus::Delivered->value, OrderStatus::Completed->value])
            ->whereDate('created_at', today())
            ->sum('total');
    }

    #[Computed]
    public function recentOrders()
    {
        return Order::where('restaurant_id', $this->rid())
            ->orderBy('created_at', 'desc')->limit(5)->get();
    }

    #[Computed]
    public function kitchenQueue()
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.restaurant_id', $this->rid())
            ->where('orders.status', OrderStatus::InPreparation->value)
            ->select([
                'order_items.product_name',
                DB::raw('SUM(order_items.quantity) as total_qty'),
                DB::raw('MIN(orders.created_at) as oldest_at'),
            ])
            ->groupBy('order_items.product_name')
            ->orderByDesc('total_qty')
            ->limit(8)
            ->get();
    }

    #[Computed]
    public function topItemsToday()
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('orders.restaurant_id', $this->rid())
            ->whereDate('orders.created_at', today())
            ->whereNotIn('orders.status', [OrderStatus::Canceled->value, OrderStatus::Draft->value])
            ->select([
                'order_items.product_name',
                DB::raw('SUM(order_items.quantity) as total_qty'),
            ])
            ->groupBy('order_items.product_name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function alerts(): array
    {
        $result = [];

        $delayed = Order::byStatus(OrderStatus::InPreparation)
            ->where('orders.restaurant_id', $this->rid())
            ->join('order_status_histories as osh', function ($join) {
                $join->on('osh.order_id', '=', 'orders.id')
                    ->where('osh.to_status', '=', OrderStatus::InPreparation->value);
            })
            ->where('osh.changed_at', '<=', now()->subMinutes(30))
            ->orderBy('osh.changed_at')
            ->get(['orders.number', 'osh.changed_at as prep_started_at']);

        foreach ($delayed as $order) {
            $minutes = (int) now()->diffInMinutes($order->prep_started_at);
            $result[] = [
                'level'   => 'error',
                'title'   => "Pedido #{$order->number} atrasado",
                'message' => "Há {$minutes} min em preparo.",
            ];
        }

        if ($this->inPreparationCount >= 5) {
            $result[] = [
                'level'   => 'warning',
                'title'   => 'Cozinha com alta carga',
                'message' => "{$this->inPreparationCount} pedidos em preparo simultâneo.",
            ];
        }

        return $result;
    }

    public function render()
    {
        return view('livewire.dashboard', [
            'openCount'          => $this->openCount,
            'inPreparationCount' => $this->inPreparationCount,
            'readyCount'         => $this->readyCount,
            'todayOrderCount'    => $this->todayOrderCount,
            'avgPrepMinutes'     => $this->avgPrepMinutes,
            'todayRevenue'       => $this->todayRevenue,
            'recentOrders'       => $this->recentOrders,
            'kitchenQueue'       => $this->kitchenQueue,
            'topItemsToday'      => $this->topItemsToday,
            'alerts'             => $this->alerts,
        ]);
    }
}
