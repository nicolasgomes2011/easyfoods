<?php

namespace Tests\Feature\Catalog;

use App\Enums\UserRole;
use App\Livewire\Catalog\ProductForm;
use App\Models\Category;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * A category slug is unique per restaurant (unique(restaurant_id, slug)), and
 * both screens that save a category derive it from the name. A name already in
 * use (same slug, inactive category included) is a validation error, never a 500.
 */
class CategoryNameUniqueTest extends TestCase
{
    use RefreshDatabase;

    private const TAKEN = 'Já existe uma categoria com esse nome.';

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        // Lowest id: the current restaurant.
        $this->restaurant = Restaurant::factory()->create(['slug' => 'casa']);
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    private function category(string $name, bool $active = true, ?Restaurant $restaurant = null): Category
    {
        return Category::create([
            'restaurant_id' => ($restaurant ?? $this->restaurant)->id,
            'name'          => $name,
            'slug'          => str($name)->slug()->toString(),
            'is_active'     => $active,
            'sort_order'    => 1,
        ]);
    }

    private function categoryCount(): int
    {
        return Category::where('restaurant_id', $this->restaurant->id)->count();
    }

    public function test_categories_screen_refuses_a_name_already_in_use(): void
    {
        $this->category('Lanches');

        Volt::test('catalog.categories')
            ->call('openCreate')
            ->set('name', 'Lanches')
            ->call('save')
            ->assertHasErrors('name')
            ->assertSee(self::TAKEN);

        $this->assertSame(1, $this->categoryCount());
    }

    public function test_categories_screen_refuses_a_name_with_the_same_slug_and_an_inactive_one(): void
    {
        $this->category('Bebidas');
        $this->category('Sobremesas', active: false);

        Volt::test('catalog.categories')
            ->set('name', 'bebidas!')
            ->call('save')
            ->assertHasErrors('name')
            ->set('name', 'Sobremesas')
            ->call('save')
            ->assertHasErrors('name');

        $this->assertSame(2, $this->categoryCount());
    }

    public function test_renaming_to_another_categorys_name_is_refused_and_keeping_the_name_still_saves(): void
    {
        $this->category('Lanches');
        $drinks = $this->category('Bebidas');

        Volt::test('catalog.categories')
            ->call('openEdit', $drinks->id)
            ->set('name', 'Lanches')
            ->call('save')
            ->assertHasErrors('name')
            ->assertSee(self::TAKEN)
            ->set('name', 'Bebidas')
            ->set('description', 'Geladas')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Bebidas', $drinks->fresh()->name);
        $this->assertSame('Geladas', $drinks->fresh()->description);
    }

    public function test_a_name_with_no_letters_or_digits_is_refused(): void
    {
        Volt::test('catalog.categories')
            ->set('name', '!!!')
            ->call('save')
            ->assertHasErrors('name');

        $this->assertSame(0, $this->categoryCount());
    }

    public function test_the_same_name_in_another_restaurant_is_allowed(): void
    {
        $other = Restaurant::factory()->create(['slug' => 'outra-casa']);
        $this->category('Lanches', restaurant: $other);

        Volt::test('catalog.categories')
            ->set('name', 'Lanches')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, $this->categoryCount());
    }

    public function test_product_form_new_category_refuses_a_name_already_in_use(): void
    {
        $this->category('Lanches');

        Livewire::test(ProductForm::class)
            ->set('newCategoryName', 'Lanches')
            ->call('createCategory')
            ->assertHasErrors('newCategoryName')
            ->assertSee(self::TAKEN)
            ->set('newCategoryName', '???')
            ->call('createCategory')
            ->assertHasErrors('newCategoryName');

        $this->assertSame(1, $this->categoryCount());
    }

    public function test_product_form_new_category_with_a_free_name_is_created_and_selected(): void
    {
        $other = Restaurant::factory()->create(['slug' => 'outra-casa']);
        $this->category('Lanches', restaurant: $other);

        $form = Livewire::test(ProductForm::class)
            ->set('newCategoryName', 'Lanches')
            ->call('createCategory')
            ->assertHasNoErrors();

        $created = Category::where('restaurant_id', $this->restaurant->id)->where('slug', 'lanches')->firstOrFail();
        $form->assertSet('category_id', (string) $created->id);
    }

    public function test_category_screens_render(): void
    {
        $this->category('Lanches');

        $this->get(route('admin.catalog.categories'))
            ->assertOk()
            ->assertSee('Lanches')
            ->assertDontSee('Undefined');

        $this->get(route('admin.catalog.products.create'))
            ->assertOk()
            ->assertDontSee('Undefined');
    }
}
