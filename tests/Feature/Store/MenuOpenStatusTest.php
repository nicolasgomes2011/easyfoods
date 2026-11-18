<?php

namespace Tests\Feature\Store;

use App\Models\Category;
use App\Models\OperatingHour;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * The menu header tells the customer whether the store is open right now and
 * what today's hours are, computed from the restaurant's operating hours.
 * 2026-11-11 is a Wednesday (weekday 3); 2026-11-10 is a Tuesday (weekday 2).
 */
class MenuOpenStatusTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->withoutHours()->create();

        $category = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name'          => 'Lanches',
            'slug'          => 'lanches',
            'is_active'     => true,
            'sort_order'    => 1,
        ]);

        Product::create([
            'restaurant_id'       => $this->restaurant->id,
            'category_id'         => $category->id,
            'name'                => 'X-Burger',
            'description'         => 'Pao, carne e queijo',
            'price'               => 25.00,
            'availability_status' => 'available',
            'sort_order'          => 1,
            'is_featured'         => false,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function hours(int $weekday, ?string $opens, ?string $closes, bool $closed = false): void
    {
        OperatingHour::create([
            'restaurant_id' => $this->restaurant->id,
            'weekday'       => $weekday,
            'opens_at'      => $opens,
            'closes_at'     => $closes,
            'is_closed'     => $closed,
        ]);
    }

    private function openAt(string $moment): bool
    {
        return $this->restaurant->fresh()->isOpenAt(Carbon::parse($moment));
    }

    public function test_store_is_open_only_between_opening_and_closing_time(): void
    {
        $this->hours(3, '11:00', '23:00');

        $this->assertFalse($this->openAt('2026-11-11 10:59'));
        $this->assertTrue($this->openAt('2026-11-11 11:00'));
        $this->assertTrue($this->openAt('2026-11-11 22:59'));
        $this->assertFalse($this->openAt('2026-11-11 23:00'));
    }

    public function test_closed_day_and_day_without_hours_count_as_closed(): void
    {
        $this->hours(3, null, null, closed: true);

        $this->assertFalse($this->openAt('2026-11-11 12:00'));
        // Thursday has no row at all.
        $this->assertFalse($this->openAt('2026-11-12 12:00'));
    }

    public function test_hours_that_cross_midnight_keep_the_store_open_on_the_next_day(): void
    {
        $this->hours(2, '18:00', '02:00');

        $this->assertFalse($this->openAt('2026-11-10 17:59'));
        $this->assertTrue($this->openAt('2026-11-10 23:30'));
        $this->assertTrue($this->openAt('2026-11-11 01:30'));
        $this->assertFalse($this->openAt('2026-11-11 02:00'));
    }

    public function test_hours_stored_with_seconds_are_read_the_same_way(): void
    {
        $this->hours(3, '11:00:00', '23:00:00');

        $this->assertTrue($this->openAt('2026-11-11 12:00'));
        $this->assertSame('11:00–23:00', $this->restaurant->fresh()->hoursOn(Carbon::parse('2026-11-11'))->rangeLabel());
    }

    public function test_menu_header_shows_open_and_todays_hours_inside_the_hours(): void
    {
        $this->hours(3, '11:00', '23:00');
        Carbon::setTestNow('2026-11-11 12:00');

        Volt::test('store.menu')
            ->assertSeeHtml('data-store-status="open"')
            ->assertSee('Aberto')
            ->assertSee('Hoje: 11:00–23:00')
            ->assertDontSeeHtml('data-store-status="closed"')
            ->assertDontSee('Fechado hoje');
    }

    public function test_menu_header_shows_closed_outside_the_hours_and_menu_stays_browsable(): void
    {
        $this->hours(3, '11:00', '23:00');
        Carbon::setTestNow('2026-11-11 23:30');

        Volt::test('store.menu')
            ->assertSeeHtml('data-store-status="closed"')
            ->assertSee('Fechado')
            ->assertSee('Hoje: 11:00–23:00')
            ->assertDontSeeHtml('data-store-status="open"')
            ->assertSee('X-Burger');

        $this->get(route('store.menu'))->assertOk()->assertSee('Fechado')->assertSee('X-Burger');
    }

    public function test_menu_header_shows_closed_today_on_a_day_without_hours(): void
    {
        $this->hours(3, null, null, closed: true);
        Carbon::setTestNow('2026-11-11 12:00');

        Volt::test('store.menu')
            ->assertSeeHtml('data-store-status="closed"')
            ->assertSee('Fechado hoje')
            ->assertDontSee('Hoje: ')
            ->assertSee('X-Burger');
    }
}
