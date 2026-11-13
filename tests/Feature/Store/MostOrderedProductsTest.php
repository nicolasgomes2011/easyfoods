<?php

namespace Tests\Feature\Store;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Restaurant;
use App\Support\MostOrderedProducts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * "Mais pedidos" on the customer menu: the products most ordered in the last
 * 30 days (by product, not by the name snapshot), only from this restaurant's
 * orders, ignoring canceled and draft orders. Only products that can be ordered
 * right now are shown; with fewer than 4 of them the section is not shown.
 */
class MostOrderedProductsTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        // The first restaurant is the storefront's current one.
        $this->restaurant = Restaurant::factory()->create();
    }

    private function product(string $name, string $category = 'Lanches', ?Restaurant $restaurant = null, array $attributes = []): Product
    {
        $restaurant ??= $this->restaurant;

        $category = Category::firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'slug' => Str::slug($category)],
            ['name' => $category, 'is_active' => true, 'sort_order' => 1],
        );

        return Product::create(array_merge([
            'restaurant_id'       => $restaurant->id,
            'category_id'         => $category->id,
            'name'                => $name,
            'price'               => 20.00,
            'availability_status' => 'available',
            'sort_order'          => 1,
            'is_featured'         => false,
        ], $attributes));
    }

    /**
     * One order with the given lines.
     *
     * @param  array<int, array{0: Product, 1: int}>  $lines  [product, quantity]
     */
    private function order(array $lines, OrderStatus $status = OrderStatus::Completed, int $daysAgo = 1, ?Restaurant $restaurant = null): Order
    {
        $this->travelTo(now()->subDays($daysAgo));

        $order = Order::factory()->create([
            'restaurant_id' => ($restaurant ?? $this->restaurant)->id,
            'status'        => $status,
        ]);

        foreach ($lines as [$product, $quantity]) {
            OrderItem::create([
                'order_id'     => $order->id,
                'product_id'   => $product->id,
                'product_name' => $product->name,
                'unit_price'   => $product->price,
                'quantity'     => $quantity,
                'subtotal'     => $product->price * $quantity,
            ]);
        }

        $this->travelBack();

        return $order;
    }

    /** Four products ordered with distinct totals: burger 9, pizza 7, juice 5, salad 3. */
    private function history(): array
    {
        $burger = $this->product('Hambúrguer');
        $pizza  = $this->product('Pizza');
        $juice  = $this->product('Suco', 'Bebidas');
        $salad  = $this->product('Salada');

        $this->order([[$burger, 4], [$pizza, 3]]);
        $this->order([[$burger, 5], [$juice, 5]]);
        $this->order([[$pizza, 4], [$salad, 3]]);

        return compact('burger', 'pizza', 'juice', 'salad');
    }

    // ── Ranking ─────────────────────────────────────────────────────────

    public function test_ranks_products_by_quantity_ordered_in_the_last_30_days(): void
    {
        ['burger' => $burger, 'pizza' => $pizza, 'juice' => $juice, 'salad' => $salad] = $this->history();

        $this->assertSame(
            [$burger->id, $pizza->id, $juice->id, $salad->id],
            MostOrderedProducts::rank($this->restaurant->id),
        );
    }

    public function test_ignores_canceled_and_draft_orders_and_orders_older_than_30_days(): void
    {
        ['burger' => $burger, 'pizza' => $pizza, 'juice' => $juice, 'salad' => $salad] = $this->history();

        $this->order([[$salad, 50]], OrderStatus::Canceled);
        $this->order([[$salad, 50]], OrderStatus::Draft);
        $this->order([[$salad, 50]], OrderStatus::Completed, daysAgo: 31);
        // Orders still in progress count.
        $this->order([[$juice, 3]], OrderStatus::InPreparation);

        $this->assertSame(
            [$burger->id, $juice->id, $pizza->id, $salad->id],
            MostOrderedProducts::rank($this->restaurant->id),
        );
    }

    public function test_groups_by_product_even_when_the_name_snapshot_changed(): void
    {
        $burger = $this->product('Hambúrguer');
        $pizza  = $this->product('Pizza');

        $this->order([[$pizza, 3]]);
        $this->order([[$burger, 2]]);
        // Same product, sold under its old name.
        $old = $this->order([[$burger, 2]]);
        $old->items()->update(['product_name' => 'X-Burguer (nome antigo)']);

        $this->assertSame([$burger->id, $pizza->id], MostOrderedProducts::rank($this->restaurant->id));
    }

    public function test_orders_of_another_restaurant_do_not_count(): void
    {
        ['burger' => $burger, 'pizza' => $pizza, 'juice' => $juice, 'salad' => $salad] = $this->history();

        $other = Restaurant::factory()->create();
        $otherProduct = $this->product('Pastel', 'Lanches', $other);
        $this->order([[$otherProduct, 100]], restaurant: $other);
        // An order of the other restaurant naming one of ours does not count either.
        $this->order([[$salad, 100]], restaurant: $other);

        $this->assertSame(
            [$burger->id, $pizza->id, $juice->id, $salad->id],
            MostOrderedProducts::rank($this->restaurant->id),
        );
        $this->assertSame([$otherProduct->id], MostOrderedProducts::rank($other->id));
    }

    public function test_ranking_is_cached_per_restaurant(): void
    {
        ['burger' => $burger, 'pizza' => $pizza, 'juice' => $juice, 'salad' => $salad] = $this->history();

        $first = MostOrderedProducts::ids($this->restaurant->id);
        $this->order([[$salad, 50]]);

        $this->assertSame($first, MostOrderedProducts::ids($this->restaurant->id));
        $this->assertTrue(Cache::has(MostOrderedProducts::cacheKey($this->restaurant->id)));

        Cache::forget(MostOrderedProducts::cacheKey($this->restaurant->id));

        $this->assertSame($salad->id, MostOrderedProducts::ids($this->restaurant->id)[0]);
    }

    // ── Menu ────────────────────────────────────────────────────────────

    public function test_menu_shows_the_most_ordered_section_in_ranking_order(): void
    {
        ['burger' => $burger, 'pizza' => $pizza, 'juice' => $juice, 'salad' => $salad] = $this->history();

        $html = Volt::test('store.menu')
            ->assertSee('Mais pedidos')
            ->assertSeeHtml('data-most-ordered')
            ->html();

        $positions = array_map(
            fn (Product $product) => strpos($html, 'data-most-ordered-product="'.$product->id.'"'),
            [$burger, $pizza, $juice, $salad],
        );

        $this->assertNotContains(false, $positions);
        $this->assertSame($positions, collect($positions)->sort()->values()->all());
    }

    public function test_menu_shows_at_most_six_products(): void
    {
        foreach (range(1, 8) as $i) {
            $this->order([[$this->product('Produto '.$i), 10 - $i]]);
        }

        $html = Volt::test('store.menu')->html();

        $this->assertSame(6, substr_count($html, 'data-most-ordered-product='));
    }

    public function test_menu_without_history_has_no_section(): void
    {
        $this->product('Hambúrguer');

        Volt::test('store.menu')
            ->assertDontSee('Mais pedidos')
            ->assertDontSeeHtml('data-most-ordered');
    }

    public function test_menu_with_fewer_than_four_products_ordered_has_no_section(): void
    {
        $burger = $this->product('Hambúrguer');
        $pizza  = $this->product('Pizza');
        $juice  = $this->product('Suco');
        $this->product('Salada');

        $this->order([[$burger, 5], [$pizza, 3], [$juice, 1]]);

        Volt::test('store.menu')->assertDontSeeHtml('data-most-ordered');
    }

    public function test_unavailable_and_archived_products_leave_the_ranking_and_the_next_one_enters(): void
    {
        ['burger' => $burger, 'pizza' => $pizza, 'juice' => $juice, 'salad' => $salad] = $this->history();
        $fries  = $this->product('Batata');
        $pastry = $this->product('Pastel');
        $this->order([[$fries, 2], [$pastry, 1]]);

        // Ranking was already cached before the products changed.
        MostOrderedProducts::ids($this->restaurant->id);

        $burger->update(['availability_status' => 'out_of_stock']);
        $pizza->update(['archived_at' => now()]);

        Volt::test('store.menu')
            ->assertDontSeeHtml('data-most-ordered-product="'.$burger->id.'"')
            ->assertDontSeeHtml('data-most-ordered-product="'.$pizza->id.'"')
            ->assertSeeHtml('data-most-ordered-product="'.$juice->id.'"')
            ->assertSeeHtml('data-most-ordered-product="'.$salad->id.'"')
            ->assertSeeHtml('data-most-ordered-product="'.$fries->id.'"')
            ->assertSeeHtml('data-most-ordered-product="'.$pastry->id.'"');
    }

    public function test_products_of_an_inactive_category_are_not_in_the_section(): void
    {
        ['burger' => $burger, 'juice' => $juice] = $this->history();
        $this->order([[$this->product('Batata'), 1]]);

        $juice->category->update(['is_active' => false]);

        Volt::test('store.menu')
            ->assertSeeHtml('data-most-ordered-product="'.$burger->id.'"')
            ->assertDontSeeHtml('data-most-ordered-product="'.$juice->id.'"');
    }

    public function test_most_ordered_products_carry_a_badge_in_their_category(): void
    {
        $this->history();
        $neverOrdered = $this->product('Batata');

        $html = Volt::test('store.menu')->html();

        // The 4 cards in the section carry no badge; the same 4 products in their categories do,
        // and a product never ordered has neither.
        $this->assertSame(4, substr_count($html, 'data-most-ordered-badge'));
        $this->assertSame(4, substr_count($html, '>Mais pedido</span>'));
        $this->assertStringNotContainsString('data-most-ordered-product="'.$neverOrdered->id.'"', $html);
    }

    public function test_section_hides_while_the_quick_search_is_used(): void
    {
        $this->history();

        $html = Volt::test('store.menu')->html();

        $this->assertMatchesRegularExpression('/<section[^>]*data-most-ordered[^>]*x-show="! term"/', $html);
    }

    public function test_real_menu_route_shows_the_section(): void
    {
        $this->history();

        $this->get(route('store.menu'))
            ->assertOk()
            ->assertDontSee('Undefined')
            ->assertSee('Mais pedidos')
            ->assertSeeHtml('data-most-ordered');
    }
}
