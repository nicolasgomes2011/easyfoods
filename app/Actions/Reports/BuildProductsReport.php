<?php

namespace App\Actions\Reports;

use App\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Products and peak hours report for one restaurant between two days
 * (inclusive):
 *
 * - top products by quantity sold and by revenue (sum of the item lines'
 *   subtotals, addons included). Lines are grouped by product; the name shown
 *   is the snapshot of the most recent order, so a product renamed in the
 *   period stays one line. Items of a deleted product (product_id null) are
 *   grouped by their name;
 * - orders by hour of the day (0–23) and by day of the week (Monday = 1 …
 *   Sunday = 7), with the busiest hour and day (the earliest one on a tie).
 *
 * Same orders as the sales report (BuildSalesReport): placed in the period
 * (orders.created_at, stored in the app timezone), canceled and draft orders
 * left out. The database sums the product lines (GROUP BY, runs on SQLite and
 * MySQL); hours and weekdays are counted here from created_at, because
 * extracting them in SQL differs between SQLite and MySQL.
 */
class BuildProductsReport
{
    /** Lines in each product ranking. */
    public const TOP = 10;

    public const WEEKDAYS = [
        1 => 'Segunda',
        2 => 'Terça',
        3 => 'Quarta',
        4 => 'Quinta',
        5 => 'Sexta',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    /**
     * @return array{
     *     from: CarbonImmutable, to: CarbonImmutable, orders: int,
     *     by_quantity: list<array{name: string, quantity: int, revenue: float}>,
     *     by_revenue: list<array{name: string, quantity: int, revenue: float}>,
     *     by_hour: array<int, int>, by_weekday: array<int, int>,
     *     peak_hour: ?int, peak_weekday: ?int,
     * }
     */
    public function execute(int $restaurantId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $from = $from->startOfDay();
        $to   = $to->startOfDay();

        $products = $this->products($restaurantId, $from, $to);

        $byQuantity = $products;
        usort($byQuantity, fn (array $a, array $b) => [$b['quantity'], $b['revenue'], $a['name']] <=> [$a['quantity'], $a['revenue'], $b['name']]);

        $byRevenue = $products;
        usort($byRevenue, fn (array $a, array $b) => [$b['revenue'], $b['quantity'], $a['name']] <=> [$a['revenue'], $a['quantity'], $b['name']]);

        $byHour    = array_fill(0, 24, 0);
        $byWeekday = array_fill_keys(array_keys(self::WEEKDAYS), 0);

        foreach ($this->placedAt($restaurantId, $from, $to) as $placedAt) {
            $at = CarbonImmutable::parse($placedAt);
            $byHour[$at->hour]++;
            $byWeekday[$at->dayOfWeekIso]++;
        }

        $orders = array_sum($byHour);

        return [
            'from'         => $from,
            'to'           => $to,
            'orders'       => $orders,
            'by_quantity'  => array_slice($byQuantity, 0, self::TOP),
            'by_revenue'   => array_slice($byRevenue, 0, self::TOP),
            'by_hour'      => $byHour,
            'by_weekday'   => $byWeekday,
            'peak_hour'    => $orders > 0 ? array_search(max($byHour), $byHour, true) : null,
            'peak_weekday' => $orders > 0 ? array_search(max($byWeekday), $byWeekday, true) : null,
        ];
    }

    /** The restaurant's orders that count, placed between the two days. */
    private function orders(int $restaurantId, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return DB::table('orders')
            ->where('orders.restaurant_id', $restaurantId)
            ->whereNotIn('orders.status', [OrderStatus::Canceled->value, OrderStatus::Draft->value])
            ->where('orders.created_at', '>=', $from->startOfDay()->toDateTimeString())
            ->where('orders.created_at', '<=', $to->endOfDay()->toDateTimeString());
    }

    /**
     * Quantity and revenue per product, unsorted.
     *
     * @return list<array{name: string, quantity: int, revenue: float}>
     */
    private function products(int $restaurantId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->orders($restaurantId, $from, $to)
            ->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->selectRaw('order_items.product_id, order_items.product_name, SUM(order_items.quantity) as quantity, SUM(order_items.subtotal) as revenue, MAX(orders.id) as last_order_id')
            ->get();

        $products = [];

        // One line per product, named after the snapshot of its most recent order.
        foreach ($rows as $row) {
            $key = $row->product_id !== null ? 'id:'.$row->product_id : 'name:'.$row->product_name;

            $products[$key] ??= ['name' => $row->product_name, 'quantity' => 0, 'revenue' => 0.0, 'last_order_id' => 0];
            $products[$key]['quantity'] += (int) $row->quantity;
            $products[$key]['revenue'] += (float) $row->revenue;

            if ((int) $row->last_order_id > $products[$key]['last_order_id']) {
                $products[$key]['name']          = $row->product_name;
                $products[$key]['last_order_id'] = (int) $row->last_order_id;
            }
        }

        return array_values(array_map(fn (array $product) => [
            'name'     => $product['name'],
            'quantity' => $product['quantity'],
            'revenue'  => round($product['revenue'], 2),
        ], $products));
    }

    /** @return list<string> created_at of each order that counts */
    private function placedAt(int $restaurantId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->orders($restaurantId, $from, $to)
            ->pluck('orders.created_at')
            ->map(fn ($value) => (string) $value)
            ->all();
    }
}
