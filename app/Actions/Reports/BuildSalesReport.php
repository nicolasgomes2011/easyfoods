<?php

namespace App\Actions\Reports;

use App\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Sales report for one restaurant between two days (inclusive): revenue (sum
 * of the order totals), number of orders and average ticket, by day, week
 * (Monday to Sunday, cut at the period edges) or month, plus the totals of the
 * period of the same length right before it.
 *
 * An order counts on the day it was placed (orders.created_at, stored in the
 * app timezone). Canceled and draft orders are left out; every other status
 * counts, including orders still in progress.
 *
 * The database only sums per day (COUNT, SUM, GROUP BY DATE(created_at): runs
 * on SQLite and MySQL); weeks and months are built from those days here.
 */
class BuildSalesReport
{
    public const GROUPS = ['day', 'week', 'month'];

    /**
     * @return array{
     *     from: CarbonImmutable, to: CarbonImmutable, group: string,
     *     previous_from: CarbonImmutable, previous_to: CarbonImmutable,
     *     orders: int, revenue: float, average_ticket: float,
     *     previous_orders: int, previous_revenue: float, previous_average_ticket: float,
     *     rows: list<array{start: string, label: string, orders: int, revenue: float, average_ticket: float}>,
     * }
     */
    public function execute(int $restaurantId, CarbonImmutable $from, CarbonImmutable $to, string $group = 'day'): array
    {
        $from  = $from->startOfDay();
        $to    = $to->startOfDay();
        $group = in_array($group, self::GROUPS, true) ? $group : 'day';

        $days         = (int) $from->diffInDays($to) + 1;
        $previousTo   = $from->subDay();
        $previousFrom = $previousTo->subDays($days - 1);

        $daily    = $this->daily($restaurantId, $from, $to);
        $previous = $this->daily($restaurantId, $previousFrom, $previousTo);

        $orders   = array_sum(array_column($daily, 'orders'));
        $revenue  = round(array_sum(array_column($daily, 'revenue')), 2);
        $pOrders  = array_sum(array_column($previous, 'orders'));
        $pRevenue = round(array_sum(array_column($previous, 'revenue')), 2);

        return [
            'from'                    => $from,
            'to'                      => $to,
            'group'                   => $group,
            'previous_from'           => $previousFrom,
            'previous_to'             => $previousTo,
            'orders'                  => $orders,
            'revenue'                 => $revenue,
            'average_ticket'          => $this->ticket($revenue, $orders),
            'previous_orders'         => $pOrders,
            'previous_revenue'        => $pRevenue,
            'previous_average_ticket' => $this->ticket($pRevenue, $pOrders),
            'rows'                    => $this->rows($daily, $from, $to, $group),
        ];
    }

    /**
     * Orders and revenue per day that had orders, keyed by Y-m-d.
     *
     * @return array<string, array{orders: int, revenue: float}>
     */
    private function daily(int $restaurantId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return DB::table('orders')
            ->where('restaurant_id', $restaurantId)
            ->whereNotIn('status', [OrderStatus::Canceled->value, OrderStatus::Draft->value])
            ->where('created_at', '>=', $from->startOfDay()->toDateTimeString())
            ->where('created_at', '<=', $to->endOfDay()->toDateTimeString())
            ->groupByRaw('DATE(created_at)')
            ->selectRaw('DATE(created_at) as day, COUNT(*) as orders, SUM(total) as revenue')
            ->get()
            ->mapWithKeys(fn ($row) => [
                substr((string) $row->day, 0, 10) => ['orders' => (int) $row->orders, 'revenue' => (float) $row->revenue],
            ])
            ->all();
    }

    /**
     * One row per day / week / month of the period, in order, including the
     * ones without orders (zero), so the table and the CSV have no gaps.
     */
    private function rows(array $daily, CarbonImmutable $from, CarbonImmutable $to, string $group): array
    {
        $buckets = [];

        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $start = match ($group) {
                'week'  => $day->startOfWeek(CarbonImmutable::MONDAY),
                'month' => $day->startOfMonth(),
                default => $day,
            };
            $start = $start->max($from);
            $key = $start->toDateString();

            $buckets[$key] ??= ['start' => $start, 'end' => $start, 'orders' => 0, 'revenue' => 0.0];
            $buckets[$key]['end'] = $day;
            $buckets[$key]['orders'] += $daily[$day->toDateString()]['orders'] ?? 0;
            $buckets[$key]['revenue'] += $daily[$day->toDateString()]['revenue'] ?? 0.0;
        }

        return array_values(array_map(function (array $bucket) use ($group) {
            $revenue = round($bucket['revenue'], 2);

            return [
                'start'          => $bucket['start']->toDateString(),
                'label'          => $this->label($bucket['start'], $bucket['end'], $group),
                'orders'         => $bucket['orders'],
                'revenue'        => $revenue,
                'average_ticket' => $this->ticket($revenue, $bucket['orders']),
            ];
        }, $buckets));
    }

    private function label(CarbonImmutable $start, CarbonImmutable $end, string $group): string
    {
        return match ($group) {
            'week'  => $start->format('d/m').' a '.$end->format('d/m/Y'),
            'month' => $start->format('m/Y'),
            default => $start->format('d/m/Y'),
        };
    }

    private function ticket(float $revenue, int $orders): float
    {
        return $orders > 0 ? round($revenue / $orders, 2) : 0.0;
    }
}
