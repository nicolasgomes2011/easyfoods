<?php

namespace Tests\Feature\Catalog;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class CategoryReorderTest extends TestCase
{
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    private function category(Restaurant $restaurant, string $name, int $sort): Category
    {
        return Category::create([
            'restaurant_id' => $restaurant->id,
            'name'          => $name,
            'slug'          => str($name)->slug()->toString(),
            'is_active'     => true,
            'sort_order'    => $sort,
        ]);
    }

    /** Ordered names as the panel renders them (sort_order, then name). */
    private function renderedOrder(Restaurant $restaurant): array
    {
        return Category::where('restaurant_id', $restaurant->id)
            ->orderBy('sort_order')->orderBy('name')
            ->pluck('name')->all();
    }

    public function test_move_down_swaps_with_the_next_category_and_normalizes(): void
    {
        $restaurant = Restaurant::factory()->create();
        $a = $this->category($restaurant, 'Aaa', 1);
        $b = $this->category($restaurant, 'Bbb', 2);
        $c = $this->category($restaurant, 'Ccc', 3);

        $this->actAsAdmin();

        Volt::test('catalog.categories')->call('moveDown', $a->id);

        $this->assertSame(['Bbb', 'Aaa', 'Ccc'], $this->renderedOrder($restaurant));
        $this->assertSame([1, 2, 3], [
            $b->fresh()->sort_order,
            $a->fresh()->sort_order,
            $c->fresh()->sort_order,
        ]);
    }

    public function test_move_up_at_the_top_is_a_noop(): void
    {
        $restaurant = Restaurant::factory()->create();
        $a = $this->category($restaurant, 'Aaa', 1);
        $this->category($restaurant, 'Bbb', 2);

        $this->actAsAdmin();

        Volt::test('catalog.categories')->call('moveUp', $a->id);

        $this->assertSame(['Aaa', 'Bbb'], $this->renderedOrder($restaurant));
        $this->assertSame(1, $a->fresh()->sort_order);
    }

    public function test_legacy_sort_order_ties_are_normalized_by_a_move(): void
    {
        $restaurant = Restaurant::factory()->create();
        // All tied at 0 — the panel falls back to name order: Aaa, Bbb, Ccc.
        $this->category($restaurant, 'Aaa', 0);
        $b = $this->category($restaurant, 'Bbb', 0);
        $c = $this->category($restaurant, 'Ccc', 0);

        $this->actAsAdmin();

        Volt::test('catalog.categories')->call('moveUp', $c->id);

        $this->assertSame(['Aaa', 'Ccc', 'Bbb'], $this->renderedOrder($restaurant));
        // Sequence persisted 1..n, so the tie never reappears.
        $this->assertSame(2, $c->fresh()->sort_order);
        $this->assertSame(3, $b->fresh()->sort_order);
    }

    public function test_a_foreign_category_cannot_be_moved(): void
    {
        // Resolve which restaurant the panel scopes to (value('id') is
        // planner-order-dependent, not first-created — BUG-007).
        $x = Restaurant::factory()->create();
        $y = Restaurant::factory()->create();
        $panelId = Restaurant::query()->value('id');
        [$panel, $foreign] = $panelId === $x->id ? [$x, $y] : [$y, $x];

        $this->category($panel, 'Aaa', 5);
        $this->category($panel, 'Bbb', 9);
        $foreignCat = $this->category($foreign, 'Zzz', 7);

        $this->actAsAdmin();

        Volt::test('catalog.categories')->call('moveDown', $foreignCat->id);

        // No-op everywhere: foreign untouched AND the panel's sequence was not
        // renormalized (5/9 preserved proves move() bailed before persisting).
        $this->assertSame(7, $foreignCat->fresh()->sort_order);
        $this->assertSame([5, 9], Category::where('restaurant_id', $panel->id)
            ->orderBy('sort_order')->pluck('sort_order')->all());
    }
}
