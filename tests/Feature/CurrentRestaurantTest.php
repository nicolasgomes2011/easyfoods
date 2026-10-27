<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Support\CurrentRestaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The panel/store "current restaurant" must resolve deterministically:
 * always the lowest id, regardless of other indexes (e.g. the unique slug)
 * the planner could otherwise serve rows from.
 */
class CurrentRestaurantTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_the_lowest_id_even_when_slug_order_differs(): void
    {
        $first  = Restaurant::factory()->create(['slug' => 'zzz-last-by-slug']);
        $second = Restaurant::factory()->create(['slug' => 'aaa-first-by-slug']);

        $this->assertLessThan($second->id, $first->id);
        $this->assertSame($first->id, CurrentRestaurant::id());
        $this->assertTrue(CurrentRestaurant::get()->is($first));
    }

    public function test_returns_null_without_a_restaurant_and_picks_up_one_created_later(): void
    {
        $this->assertNull(CurrentRestaurant::id());
        $this->assertNull(CurrentRestaurant::get());

        $restaurant = Restaurant::factory()->create();

        $this->assertSame($restaurant->id, CurrentRestaurant::id());
    }

    public function test_get_returns_fresh_attributes(): void
    {
        $restaurant = Restaurant::factory()->create(['name' => 'Antes']);
        CurrentRestaurant::id();

        $restaurant->update(['name' => 'Depois']);

        $this->assertSame('Depois', CurrentRestaurant::get()->name);
    }
}
