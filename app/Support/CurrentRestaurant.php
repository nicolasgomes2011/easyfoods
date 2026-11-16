<?php

namespace App\Support;

use App\Models\Restaurant;
use Illuminate\Support\Facades\Auth;

/**
 * Single source of truth for "the restaurant this request operates on".
 *
 * Panel: the restaurant of the logged-in staff member (users.restaurant_id,
 * web guard). It is read from the authenticated user on every call, so a
 * different user in the same request (e.g. tests) gets their own restaurant.
 *
 * Guests, storefront customers and staff without a restaurant yet: the default
 * restaurant — the lowest id, with an explicit ORDER BY so it stays
 * deterministic across databases and query planners. That id is cached for
 * the request (the class is bound as a scoped singleton); "no restaurant yet"
 * is not cached, so a restaurant created mid-request is picked up.
 *
 * Work with no logged-in user that runs for every restaurant (scheduler jobs)
 * wraps each restaurant's part in runAs(), so the tenant scope of the
 * restaurant-owned models (BelongsToRestaurant) follows that restaurant.
 */
final class CurrentRestaurant
{
    private ?int $id = null;

    /** Set by runAs() while a job works on one specific restaurant. */
    private ?int $override = null;

    public static function id(): ?int
    {
        return app(self::class)->resolveId();
    }

    /**
     * Run $callback as if $restaurantId were the current restaurant, then
     * restore the previous one (also when the callback throws).
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public static function runAs(int $restaurantId, callable $callback): mixed
    {
        $current  = app(self::class);
        $previous = $current->override;
        $current->override = $restaurantId;

        try {
            return $callback();
        } finally {
            $current->override = $previous;
        }
    }

    public static function get(): ?Restaurant
    {
        $id = self::id();

        return $id === null ? null : Restaurant::find($id);
    }

    private function resolveId(): ?int
    {
        if ($this->override !== null) {
            return $this->override;
        }

        $staffRestaurantId = Auth::guard('web')->user()?->restaurant_id;

        if ($staffRestaurantId !== null) {
            return (int) $staffRestaurantId;
        }

        return $this->id ??= Restaurant::query()->orderBy('id')->value('id');
    }
}
