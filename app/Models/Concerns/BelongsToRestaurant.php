<?php

namespace App\Models\Concerns;

use App\Models\Scopes\RestaurantScope;
use App\Support\CurrentRestaurant;
use Illuminate\Database\Eloquent\Model;

/**
 * A model owned by a restaurant (restaurant_id column).
 *
 * - Queries are fenced to the current restaurant (RestaurantScope).
 * - A new row without restaurant_id gets the current restaurant's id. An
 *   explicit restaurant_id is kept as given.
 */
trait BelongsToRestaurant
{
    public static function bootBelongsToRestaurant(): void
    {
        static::addGlobalScope(new RestaurantScope);

        static::creating(function (Model $model) {
            if ($model->getAttribute('restaurant_id') === null) {
                $model->setAttribute('restaurant_id', CurrentRestaurant::id());
            }
        });
    }
}
