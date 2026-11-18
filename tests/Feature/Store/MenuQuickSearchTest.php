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
 * Quick search on the menu filters the products in the browser (Alpine), with
 * no server request while typing. Each product carries its name + description
 * without accents and in lower case, so "hamb" finds "Hambúrguer".
 */
class MenuQuickSearchTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
    }

    private function product(string $category, string $name, ?string $description = null): Product
    {
        $category = Category::firstOrCreate(
            ['restaurant_id' => $this->restaurant->id, 'slug' => Str::slug($category)],
            ['name' => $category, 'is_active' => true, 'sort_order' => 1],
        );

        return Product::create([
            'restaurant_id'       => $this->restaurant->id,
            'category_id'         => $category->id,
            'name'                => $name,
            'description'         => $description,
            'price'               => 20.00,
            'availability_status' => 'available',
            'sort_order'          => 1,
            'is_featured'         => false,
        ]);
    }

    public function test_search_text_has_no_accents_or_capitals(): void
    {
        $burger = $this->product('Lanches', 'Hambúrguer Artesanal', 'Pão brioche e   cebola caramelizada');
        $salad = $this->product('Lanches', 'X-Salada');
        $juice = $this->product('Bebidas', 'Suco de Maçã', 'Natural');

        $this->assertSame('hamburguer artesanal pao brioche e cebola caramelizada', $burger->searchText());
        $this->assertStringContainsString('hamb', $burger->searchText());
        $this->assertStringNotContainsString('hamb', $salad->searchText());
        $this->assertSame('x-salada', $salad->searchText());
        $this->assertSame('suco de maca natural', $juice->searchText());
    }

    public function test_menu_has_a_search_field_filtered_in_the_browser_only(): void
    {
        $this->product('Lanches', 'Hambúrguer Artesanal');

        $html = Volt::test('store.menu')->html();

        $this->assertMatchesRegularExpression('/<input[^>]*data-menu-search[^>]*>/', $html);
        preg_match('/<input[^>]*data-menu-search[^>]*>/', $html, $input);
        $this->assertStringContainsString('x-model="q"', $input[0]);
        $this->assertStringNotContainsString('wire:', $input[0]);
    }

    public function test_each_product_carries_its_search_text_and_hides_when_it_does_not_match(): void
    {
        $this->product('Lanches', 'Hambúrguer Artesanal', 'Pão brioche');
        $this->product('Bebidas', 'Suco de Maçã');

        Volt::test('store.menu')
            ->assertSeeHtml('data-search="hamburguer artesanal pao brioche"')
            ->assertSeeHtml('data-search="suco de maca"')
            ->assertSeeHtml('x-show="matches($el.dataset.search)"');
    }

    public function test_sections_tabs_and_empty_message_follow_the_search(): void
    {
        $this->product('Lanches', 'Hambúrguer Artesanal');
        $this->product('Bebidas', 'Suco de Maçã');

        Volt::test('store.menu')
            ->assertSeeHtml('x-show="anyMatch($el)"')
            ->assertSeeHtml("x-show=\"anyMatch(document.getElementById('cat-lanches'))\"")
            ->assertSeeHtml('x-show="! anyMatch($root)"')
            ->assertSee('Nenhum produto encontrado')
            ->assertSee('Limpar');
    }

    public function test_menu_page_shows_the_search_field(): void
    {
        $this->product('Lanches', 'Hambúrguer Artesanal');

        $this->get(route('store.menu'))
            ->assertOk()
            ->assertSee('data-menu-search', false)
            ->assertSee('data-search="hamburguer artesanal"', false)
            ->assertSee('Hambúrguer Artesanal')
            ->assertDontSee('Undefined');
    }

    public function test_menu_without_products_has_no_search_field(): void
    {
        Volt::test('store.menu')
            ->assertSee('Nenhum produto disponível no momento.')
            ->assertDontSeeHtml('data-menu-search');
    }
}
