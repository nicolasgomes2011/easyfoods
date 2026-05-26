<?php

namespace App\Livewire;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Dashboard extends Component
{
    #[Computed]
    public function openCount(): int
    {
        return Order::byStatus(OrderStatus::PendingConfirmation)->count();
    }

    #[Computed]
    public function inPreparationCount(): int
    {
        return Order::byStatus(OrderStatus::InPreparation)->count();
    }

    #[Computed]
    public function readyCount(): int
    {
        return Order::byStatus(OrderStatus::ReadyForPickup)->count();
    }

    #[Computed]
    public function todayOrderCount(): int
    {
        return Order::whereDate('created_at', today())
            ->whereNotIn('status', [OrderStatus::Canceled->value, OrderStatus::Draft->value])
            ->count();
    }

    #[Computed]
    public function avgPrepMinutes(): ?int
    {
        $orders = Order::whereNotNull('ready_at')
            ->whereDate('ready_at', today())
            ->get(['created_at', 'ready_at']);

        if ($orders->isEmpty()) {
            return null;
        }

        return (int) round($orders->avg(
            fn ($o) => $o->created_at->diffInMinutes($o->ready_at)
        ));
    }

    #[Computed]
    public function todayRevenue(): float
    {
        return (float) Order::whereIn('status', [
            OrderStatus::Delivered->value,
            OrderStatus::Completed->value,
        ])->whereDate('created_at', today())->sum('total');
    }

    #[Computed]
    public function recentOrders()
    {
        return Order::orderBy('created_at', 'desc')->limit(5)->get();
    }

    #[Computed]
    public function kitchenQueue()
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
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
            ->where('updated_at', '<=', now()->subMinutes(30))
            ->get(['number', 'updated_at']);

        foreach ($delayed as $order) {
            $result[] = [
                'level'   => 'error',
                'title'   => "Pedido #{$order->number} atrasado",
                'message' => 'Mais de 30 min em preparo sem atualização de status.',
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
