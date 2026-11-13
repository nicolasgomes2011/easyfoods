<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Product;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * "Mais pedidos" on the customer menu: the restaurant's products ranked by the
 * quantity ordered in the last 30 days, ignoring canceled and draft orders.
 *
 * Grouped by product_id (not by the name snapshot), so a renamed product stays
 * one entry and links to the current product. Both the order and the product
 * must belong to the restaurant. The ranking is cached for an hour per
 * restaurant; whether a product can be ordered right now is checked by the
 * menu on every render, so a paused product leaves the section at once and
 * the next candidate takes its place.
 *
 * Plain aggregate SQL (SUM, GROUP BY) that runs on SQLite and MySQL.
 */
final class MostOrderedProducts
{
    public const WINDOW_DAYS = 30;

    public const CACHE_SECONDS = 3600;

    /** Products shown in the section. */
    public const LIMIT = 6;

    /** Below this many orderable products the section is not shown (new restaurant). */
    public const MIN_PRODUCTS = 4;

    /** Ranked products kept in the cache, so unavailable ones can be skipped without a new query. */
    public const CANDIDATES = 12;

    /**
     * Product ids of the restaurant, most ordered first (cached for an hour).
     *
     * @return list<int>
     */
    public static function ids(int $restaurantId): array
    {
        return Cache::remember(
            self::cacheKey($restaurantId),
            self::CACHE_SECONDS,
            fn () => self::rank($restaurantId),
        );
    }

    /**
     * The section's products: the ranked ones that are on the menu and can be
     * ordered right now (available, in one of the menu's categories), at most
     * LIMIT, ranking order kept. Empty below MIN_PRODUCTS.
     *
     * @param  Collection<int, Product>  $menuProducts  the menu's non-archived products
     * @param  Collection<int, int>  $categoryIds  ids of the (active) categories shown on the menu
     * @return Collection<int, Product>
     */
    public static function forMenu(int $restaurantId, Collection $menuProducts, Collection $categoryIds): Collection
    {
        $categoryIds = $categoryIds->flip();
        $orderable = $menuProducts
            ->filter(fn (Product $product) => $product->isAvailable() && $categoryIds->has($product->category_id))
            ->keyBy('id');

        $top = collect(self::ids($restaurantId))
            ->map(fn (int $id) => $orderable->get($id))
            ->filter()
            ->take(self::LIMIT)
            ->values();

        return $top->count() >= self::MIN_PRODUCTS ? $top : collect();
    }

    public static function cacheKey(int $restaurantId): string
    {
        return 'store.most-ordered.'.$restaurantId;
    }

    /**
     * The ranking itself, uncached: up to CANDIDATES product ids, highest
     * quantity first (ties by product id, so the order is stable).
     *
     * @return list<int>
     */
    public static function rank(int $restaurantId, ?CarbonInterface $now = null): array
    {
        $now ??= now();

        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.restaurant_id', $restaurantId)
            ->where('products.restaurant_id', $restaurantId)
            ->whereNotIn('orders.status', [OrderStatus::Canceled->value, OrderStatus::Draft->value])
            ->where('orders.created_at', '>=', $now->copy()->subDays(self::WINDOW_DAYS))
            ->groupBy('order_items.product_id')
            ->orderByRaw('SUM(order_items.quantity) DESC')
            ->orderBy('order_items.product_id')
            ->limit(self::CANDIDATES)
            ->pluck('order_items.product_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
