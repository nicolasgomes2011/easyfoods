<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use App\Support\CurrentRestaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The panel/store "current restaurant" must resolve deterministically: the
 * logged-in staff member's restaurant (EF-81), otherwise the lowest id,
 * regardless of other indexes (e.g. the unique slug) the planner could
 * otherwise serve rows from.
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

    public function test_logged_in_staff_resolves_to_their_own_restaurant(): void
    {
        Restaurant::factory()->create(); // lowest id: the default
        $theirs = Restaurant::factory()->create();

        $this->actingAs(User::factory()->create(['restaurant_id' => $theirs->id]));

        $this->assertSame($theirs->id, CurrentRestaurant::id());
        $this->assertTrue(CurrentRestaurant::get()->is($theirs));
    }

    public function test_guests_and_staff_without_a_restaurant_get_the_default_one(): void
    {
        $default = Restaurant::factory()->create();
        Restaurant::factory()->create();

        $this->assertSame($default->id, CurrentRestaurant::id()); // guest (storefront)

        $this->actingAs(User::factory()->create()); // no restaurant_id yet
        $this->assertSame($default->id, CurrentRestaurant::id());
    }

    public function test_switching_the_logged_in_user_switches_the_restaurant(): void
    {
        $first  = Restaurant::factory()->create();
        $second = Restaurant::factory()->create();

        $this->actingAs(User::factory()->create(['restaurant_id' => $second->id]));
        $this->assertSame($second->id, CurrentRestaurant::id());

        $this->actingAs(User::factory()->create(['restaurant_id' => $first->id]));
        $this->assertSame($first->id, CurrentRestaurant::id());
    }

    public function test_get_returns_fresh_attributes(): void
    {
        $restaurant = Restaurant::factory()->create(['name' => 'Antes']);
        CurrentRestaurant::id();

        $restaurant->update(['name' => 'Depois']);

        $this->assertSame('Depois', CurrentRestaurant::get()->name);
    }
}
