<?php

namespace Tests\Feature\Store;

use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * The menu is one page: every category with products gets its own section, in
 * the admin order, and the category tabs scroll to the section in the browser
 * instead of filtering on the server.
 */
class MenuCategorySectionsTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
    }

    private function category(string $name, int $sortOrder, bool $active = true): Category
    {
        return Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name'          => $name,
            'slug'          => Str::slug($name),
            'is_active'     => $active,
            'sort_order'    => $sortOrder,
        ]);
    }

    private function product(Category $category, string $name, int $sortOrder = 1, bool $archived = false): Product
    {
        return Product::create([
            'restaurant_id'       => $this->restaurant->id,
            'category_id'         => $category->id,
            'name'                => $name,
            'price'               => 20.00,
            'availability_status' => 'available',
            'sort_order'          => $sortOrder,
            'is_featured'         => false,
            'archived_at'         => $archived ? now() : null,
        ]);
    }

    /** Bebidas is created first but sorted after Lanches in the admin. */
    private function seedMenu(): void
    {
        $drinks = $this->category('Bebidas', 2);
        $burgers = $this->category('Lanches', 1);

        $this->product($drinks, 'Coca-Cola');
        $this->product($burgers, 'X-Salada', 2);
        $this->product($burgers, 'X-Burger', 1);
    }

    public function test_every_category_gets_a_section_in_the_admin_order(): void
    {
        $this->seedMenu();

        Volt::test('store.menu')
            ->assertSeeHtmlInOrder([
                'id="cat-lanches"', 'X-Burger', 'X-Salada',
                'id="cat-bebidas"', 'Coca-Cola',
            ]);
    }

    public function test_category_without_visible_products_or_inactive_has_no_section(): void
    {
        $this->seedMenu();
        $this->product($this->category('Sobremesas', 3), 'Pudim', archived: true);
        $this->product($this->category('Antigos', 4, active: false), 'Misto Quente');

        Volt::test('store.menu')
            ->assertDontSeeHtml('id="cat-sobremesas"')
            ->assertDontSeeHtml('id="cat-antigos"')
            ->assertDontSee('Pudim')
            ->assertDontSee('Misto Quente');
    }

    public function test_tabs_scroll_to_the_section_without_a_livewire_request(): void
    {
        $this->seedMenu();

        Volt::test('store.menu')
            ->assertSeeHtml('data-category-tab="lanches"')
            ->assertSeeHtml('data-category-tab="bebidas"')
            ->assertSeeHtml("@click=\"go('bebidas')\"")
            ->assertDontSeeHtml("\$set('category'");
    }

    public function test_menu_page_shows_every_section(): void
    {
        $this->seedMenu();

        $this->get(route('store.menu'))
            ->assertOk()
            ->assertSeeInOrder(['Lanches', 'X-Burger', 'Bebidas', 'Coca-Cola'])
            ->assertDontSee('Undefined');
    }

    public function test_category_link_still_shows_the_whole_menu(): void
    {
        $this->seedMenu();

        $this->get(route('store.menu', ['category' => 'bebidas']))
            ->assertOk()
            ->assertSee('X-Burger')
            ->assertSee('Coca-Cola')
            ->assertSee('data-scroll-to="bebidas"', false);
    }

    public function test_menu_without_products_shows_the_empty_message(): void
    {
        $this->category('Lanches', 1);

        Volt::test('store.menu')
            ->assertSee('Nenhum produto disponível no momento.')
            ->assertDontSeeHtml('data-category-tab=');
    }
}
