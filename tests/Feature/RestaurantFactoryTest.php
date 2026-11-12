<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * restaurants.slug is unique, and many tests create two or more restaurants.
 * The pt_BR faker company() draws from a small set of names, so a slug taken
 * from the name alone repeats at random and breaks those tests.
 */
class RestaurantFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_many_factory_restaurants_never_share_a_slug(): void
    {
        $restaurants = Restaurant::factory()->withoutHours()->count(200)->create();

        $this->assertCount(200, $restaurants->pluck('slug')->unique());
    }

    public function test_factory_slug_is_the_name_slug_plus_a_url_safe_suffix(): void
    {
        $restaurant = Restaurant::factory()->make();

        $this->assertStringStartsWith(Str::slug($restaurant->name).'-', $restaurant->slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $restaurant->slug);
    }
}
