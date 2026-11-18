<?php

namespace App\Models\Scopes;

use App\Support\CurrentRestaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Tenant fence: every query on a restaurant-owned model only sees the rows of
 * the current restaurant (CurrentRestaurant: the logged-in staff member's
 * restaurant, otherwise the default one). With no restaurant at all (empty
 * install) the query is left as is.
 *
 * Code that must work across restaurants (scheduler jobs, console commands)
 * opts out with withoutGlobalScope(RestaurantScope::class).
 */
class RestaurantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $restaurantId = CurrentRestaurant::id();

        if ($restaurantId !== null) {
            $builder->where($model->qualifyColumn('restaurant_id'), $restaurantId);
        }
    }
}
