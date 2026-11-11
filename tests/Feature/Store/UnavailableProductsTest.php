<?php

namespace Tests\Feature\Store;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Paused / out-of-stock products stay on the menu, greyed out with a label and
 * no add action, so the customer knows they exist. Archived products stay
 * hidden. The server still refuses to put a non-orderable product in the cart.
 */
class UnavailableProductsTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();

        $this->category = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name'          => 'Lanches',
            'slug'          => 'lanches',
            'is_active'     => true,
            'sort_order'    => 1,
        ]);
    }

    private function product(string $name, string $status = 'available', array $extra = []): Product
    {
        return Product::create(array_merge([
            'restaurant_id'       => $this->restaurant->id,
            'category_id'         => $this->category->id,
            'name'                => $name,
            'price'               => 25.00,
            'availability_status' => $status,
            'sort_order'          => 1,
            'is_featured'         => false,
        ], $extra));
    }

    public function test_paused_product_shows_greyed_out_with_label_and_no_add_action(): void
    {
        $available = $this->product('X-Burger');
        $paused    = $this->product('X-Salada', 'unavailable');

        $this->get(route('store.menu'))
            ->assertOk()
            ->assertSee('X-Burger')
            ->assertSee('X-Salada')
            ->assertSee('Indisponível no momento')
            ->assertSee("openProduct({$available->id})", false)
            ->assertDontSee("openProduct({$paused->id})", false);
    }

    public function test_out_of_stock_product_is_also_shown_as_unavailable(): void
    {
        $soldOut = $this->product('Milkshake', 'out_of_stock');

        $this->get(route('store.menu'))
            ->assertOk()
            ->assertSee('Milkshake')
            ->assertSee('Indisponível no momento')
            ->assertDontSee("openProduct({$soldOut->id})", false);
    }

    public function test_available_product_has_no_unavailable_label(): void
    {
        $this->product('X-Burger');

        $this->get(route('store.menu'))
            ->assertOk()
            ->assertSee('X-Burger')
            ->assertDontSee('Indisponível no momento');
    }

    public function test_archived_product_is_not_shown(): void
    {
        $this->product('X-Burger');
        $this->product('X-Antigo', 'available', ['archived_at' => now()]);
        $this->product('X-Antigo Pausado', 'unavailable', ['archived_at' => now()]);

        $this->get(route('store.menu'))
            ->assertOk()
            ->assertSee('X-Burger')
            ->assertDontSee('X-Antigo');
    }

    public function test_category_with_only_paused_products_keeps_its_tab(): void
    {
        $this->product('X-Salada', 'unavailable');

        $this->get(route('store.menu'))
            ->assertOk()
            ->assertSee('Lanches')
            ->assertSee('X-Salada')
            ->assertDontSee('Nenhum produto disponível no momento.');
    }

    public function test_adding_a_paused_product_through_the_component_is_refused(): void
    {
        $paused = $this->product('X-Salada', 'unavailable');

        Volt::test('store.menu')
            ->call('openProduct', $paused->id)
            ->call('addToCart', $paused->id)
            ->assertHasNoErrors();

        $this->assertSame(0, CartItem::count());
    }
}
