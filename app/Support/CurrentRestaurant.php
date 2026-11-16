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
 */
final class CurrentRestaurant
{
    private ?int $id = null;

    public static function id(): ?int
    {
        return app(self::class)->resolveId();
    }

    public static function get(): ?Restaurant
    {
        $id = self::id();

        return $id === null ? null : Restaurant::find($id);
    }

    private function resolveId(): ?int
    {
        $staffRestaurantId = Auth::guard('web')->user()?->restaurant_id;

        if ($staffRestaurantId !== null) {
            return (int) $staffRestaurantId;
        }

        return $this->id ??= Restaurant::query()->orderBy('id')->value('id');
    }
}
