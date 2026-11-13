<?php

namespace App\Actions\Waste;

use App\Enums\IngredientCategory;
use App\Enums\IngredientUnit;
use App\Enums\WasteReason;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Waste report for one restaurant: money lost between two days (inclusive),
 * ranked by ingredient, category and reason, plus the total of the period of
 * the same length right before it.
 *
 * Lost money = Σ quantity × unit_cost, both snapshotted on each entry, so a
 * later price change does not rewrite the past. A correction (adjusts_id)
 * counts in the period of the entry it corrects, so correcting last week's
 * entry today fixes last week. The category is the ingredient's current one
 * (entries do not snapshot it); without an ingredient it falls under "Outros".
 *
 * Aggregates are plain SQL (SUM, COALESCE, CASE) that run on SQLite and MySQL.
 */
class BuildWasteReport
{
    /** Below this a net quantity is zero (quantities have 3 decimals). */
    private const ZERO = 0.0005;

    /**
     * @return array{
     *     from: CarbonImmutable, to: CarbonImmutable,
     *     previous_from: CarbonImmutable, previous_to: CarbonImmutable,
     *     total: float, previous_total: float,
     *     by_ingredient: list<array{label: string, quantity: float, unit: string, cost: float, share: float}>,
     *     by_category: list<array{label: string, cost: float, share: float}>,
     *     by_reason: list<array{label: string, cost: float, share: float}>,
     * }
     */
    public function execute(int $restaurantId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $from = $from->startOfDay();
        $to   = $to->startOfDay();

        $days         = (int) $from->diffInDays($to) + 1;
        $previousTo   = $from->subDay();
        $previousFrom = $previousTo->subDays($days - 1);

        $total = $this->total($this->entries($restaurantId, $from, $to));

        return [
            'from'           => $from,
            'to'             => $to,
            'previous_from'  => $previousFrom,
            'previous_to'    => $previousTo,
            'total'          => $total,
            'previous_total' => $this->total($this->entries($restaurantId, $previousFrom, $previousTo)),
            'by_ingredient'  => $this->byIngredient($restaurantId, $from, $to, $total),
            'by_category'    => $this->byCategory($restaurantId, $from, $to, $total),
            'by_reason'      => $this->byReason($restaurantId, $from, $to, $total),
        ];
    }

    /**
     * The restaurant's entries whose discard happened between the two days: the
     * entry's own time, or for a correction the time of the entry it corrects.
     */
    private function entries(int $restaurantId, CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return DB::table('waste_entries as w')
            ->leftJoin('waste_entries as original', 'original.id', '=', 'w.adjusts_id')
            ->where('w.restaurant_id', $restaurantId)
            ->whereRaw('COALESCE(original.created_at, w.created_at) >= ?', [$from->startOfDay()->toDateTimeString()])
            ->whereRaw('COALESCE(original.created_at, w.created_at) <= ?', [$to->endOfDay()->toDateTimeString()]);
    }

    private function total(Builder $entries): float
    {
        return round((float) $entries->sum(DB::raw('w.quantity * w.unit_cost')), 2);
    }

    private function byIngredient(int $restaurantId, CarbonImmutable $from, CarbonImmutable $to, float $total): array
    {
        $rows = $this->entries($restaurantId, $from, $to)
            ->leftJoin('ingredients as i', 'i.id', '=', 'w.ingredient_id')
            // One row per ingredient (and unit); entries without an ingredient group by their snapshot name.
            ->groupByRaw('w.ingredient_id, w.unit, CASE WHEN w.ingredient_id IS NULL THEN w.ingredient_name END')
            ->selectRaw('COALESCE(MAX(i.name), MAX(w.ingredient_name)) as label, w.unit as unit, SUM(w.quantity) as quantity, SUM(w.quantity * w.unit_cost) as cost')
            ->get();

        return $this->ranked($rows, $total, fn ($row) => [
            'label'    => (string) $row->label,
            'quantity' => round((float) $row->quantity, 3),
            'unit'     => IngredientUnit::tryFrom((string) $row->unit)?->symbol() ?? (string) $row->unit,
        ]);
    }

    private function byCategory(int $restaurantId, CarbonImmutable $from, CarbonImmutable $to, float $total): array
    {
        $category = "COALESCE(i.category, '".IngredientCategory::Other->value."')";

        $rows = $this->entries($restaurantId, $from, $to)
            ->leftJoin('ingredients as i', 'i.id', '=', 'w.ingredient_id')
            ->groupByRaw($category)
            ->selectRaw($category.' as category, SUM(w.quantity) as quantity, SUM(w.quantity * w.unit_cost) as cost')
            ->get();

        return $this->ranked($rows, $total, fn ($row) => [
            'label' => IngredientCategory::tryFrom((string) $row->category)?->label() ?? (string) $row->category,
        ]);
    }

    private function byReason(int $restaurantId, CarbonImmutable $from, CarbonImmutable $to, float $total): array
    {
        $rows = $this->entries($restaurantId, $from, $to)
            ->groupBy('w.reason')
            ->selectRaw('w.reason as reason, SUM(w.quantity) as quantity, SUM(w.quantity * w.unit_cost) as cost')
            ->get();

        return $this->ranked($rows, $total, fn ($row) => [
            'label' => WasteReason::tryFrom((string) $row->reason)?->label() ?? (string) $row->reason,
        ]);
    }

    /**
     * Drops groups whose entries were all corrected to zero (a net quantity is
     * never negative, so a zero sum means nothing was lost), adds cost and share
     * of the period total, and sorts by cost, highest first.
     */
    private function ranked($rows, float $total, callable $describe): array
    {
        return $rows
            ->filter(fn ($row) => abs((float) $row->quantity) >= self::ZERO)
            ->map(function ($row) use ($total, $describe) {
                $cost = round((float) $row->cost, 2);

                return $describe($row) + [
                    'cost'  => $cost,
                    'share' => $total > 0 ? round($cost / $total * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('cost')
            ->values()
            ->all();
    }
}
