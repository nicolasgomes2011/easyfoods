<?php

namespace App\Support;

use App\Models\Restaurant;

/**
 * Single source of truth for "the restaurant this request operates on".
 *
 * The platform is still single-tenant per install, so this resolves to the
 * lowest restaurant id — an explicit ORDER BY keeps it deterministic across
 * databases and query planners. The id is cached for the request (the class
 * is bound as a scoped singleton); "no restaurant yet" is not cached, so a
 * restaurant created mid-request is picked up.
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
        return $this->id ??= Restaurant::query()->orderBy('id')->value('id');
    }
}
