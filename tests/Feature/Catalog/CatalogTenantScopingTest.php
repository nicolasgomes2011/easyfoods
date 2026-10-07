<?php

namespace Tests\Feature\Catalog;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\UserRole;
use App\Livewire\Catalog\ProductList;
use App\Models\AddonGroup;
use App\Models\AddonOption;
use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Catalog actions must never reach across restaurants. Every lookup behind a
 * Livewire action is fenced by restaurant_id; these tests hand each action a
 * foreign id and assert it is rejected and nothing changes.
 */
class CatalogTenantScopingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The panel scopes to Restaurant::query()->value('id'), which is
     * planner-order-dependent, NOT first-created (BUG-007). Resolve which
     * restaurant the panel will use and return [panel, foreign].
     */
    private function panelAndForeign(): array
    {
        $a = Restaurant::factory()->create();
        $b = Restaurant::factory()->create();

        $panelId = Restaurant::query()->value('id');

        return $panelId === $a->id ? [$a, $b] : [$b, $a];
    }

    private function actAsAdmin(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    private function category(Restaurant $restaurant, int $sort = 1): Category
    {
        return Category::create([
            'restaurant_id' => $restaurant->id,
            'name'          => "Categoria {$restaurant->id}-{$sort}",
            'slug'          => "categoria-{$restaurant->id}-{$sort}",
            'is_active'     => true,
            'sort_order'    => $sort,
        ]);
    }

    private function product(Restaurant $restaurant, ?Category $category = null): Product
    {
        return Product::create([
            'restaurant_id'       => $restaurant->id,
            'category_id'         => ($category ?? $this->category($restaurant))->id,
            'name'                => 'Produto '.uniqid(),
            'price'               => 10.00,
            'availability_status' => ProductAvailabilityStatus::Available,
            'sort_order'          => 0,
            'is_featured'         => false,
        ]);
    }

    private function addonGroup(Product $product): AddonGroup
    {
        return AddonGroup::create([
            'product_id'  => $product->id,
            'name'        => 'Grupo '.uniqid(),
            'required'    => false,
            'min_choices' => 0,
            'max_choices' => 2,
            'sort_order'  => 1,
            'is_active'   => true,
        ]);
    }

    /** Call a component action expecting the scoped lookup (findOrFail) to 404. */
    private function assertActionRejected(callable $action): void
    {
        $action()->assertStatus(404);
    }

    public function test_product_toggle_and_delete_reject_foreign_products(): void
    {
        [, $foreign] = $this->panelAndForeign();
        $foreignProduct = $this->product($foreign);

        $this->actAsAdmin();

        $this->assertActionRejected(
            fn () => Livewire::test(ProductList::class)->call('toggleAvailability', $foreignProduct->id)
        );

        $this->assertActionRejected(
            fn () => Livewire::test(ProductList::class)->call('delete', $foreignProduct->id)
        );

        $foreignProduct->refresh();
        $this->assertSame(ProductAvailabilityStatus::Available, $foreignProduct->availability_status);
        $this->assertDatabaseHas('products', ['id' => $foreignProduct->id]);
    }

    public function test_category_actions_reject_foreign_categories(): void
    {
        [, $foreign] = $this->panelAndForeign();
        $foreignCategory = $this->category($foreign);

        $this->actAsAdmin();

        $this->assertActionRejected(
            fn () => Volt::test('catalog.categories')->call('toggleActive', $foreignCategory->id)
        );

        $this->assertActionRejected(
            fn () => Volt::test('catalog.categories')->call('delete', $foreignCategory->id)
        );

        // Update path: editingId pointing at a foreign category must not save.
        $this->assertActionRejected(
            fn () => Volt::test('catalog.categories')
                ->set('editingId', (string) $foreignCategory->id)
                ->set('name', 'Hacked')
                ->call('save'),
            'save must not update a foreign category.'
        );

        $foreignCategory->refresh();
        $this->assertTrue($foreignCategory->is_active);
        $this->assertNotSame('Hacked', $foreignCategory->name);
    }

    public function test_new_category_sort_order_ignores_other_restaurants(): void
    {
        [$panel, $foreign] = $this->panelAndForeign();
        $this->category($panel, sort: 2);
        $this->category($foreign, sort: 99); // must not leak into the panel's ordering

        $this->actAsAdmin();

        Volt::test('catalog.categories')
            ->set('name', 'Sobremesas')
            ->call('save')
            ->assertHasNoErrors();

        $created = Category::where('restaurant_id', $panel->id)->where('name', 'Sobremesas')->firstOrFail();
        $this->assertSame(3, $created->sort_order);
    }

    public function test_addon_group_cannot_attach_to_a_foreign_product(): void
    {
        [, $foreign] = $this->panelAndForeign();
        $foreignProduct = $this->product($foreign);

        $this->actAsAdmin();

        Volt::test('catalog.addons')
            ->set('groupName', 'Molhos')
            ->set('groupProductId', (string) $foreignProduct->id)
            ->call('saveGroup')
            ->assertHasErrors(['groupProductId']);

        $this->assertDatabaseMissing('addon_groups', ['product_id' => $foreignProduct->id]);
    }

    public function test_addon_group_rejects_min_choices_greater_than_max(): void
    {
        [$panel] = $this->panelAndForeign();
        $product = $this->product($panel);

        $this->actAsAdmin();

        Volt::test('catalog.addons')
            ->set('groupName', 'Molhos')
            ->set('groupProductId', (string) $product->id)
            ->set('groupMinChoices', 3)
            ->set('groupMaxChoices', 1)
            ->call('saveGroup')
            ->assertHasErrors(['groupMaxChoices']);

        $this->assertDatabaseMissing('addon_groups', ['product_id' => $product->id]);
    }

    public function test_addon_group_and_option_actions_reject_foreign_records(): void
    {
        [, $foreign] = $this->panelAndForeign();
        $foreignGroup = $this->addonGroup($this->product($foreign));
        $foreignOption = AddonOption::create([
            'addon_group_id' => $foreignGroup->id,
            'name'           => 'Cheddar',
            'price'          => 3.00,
            'is_active'      => true,
            'sort_order'     => 1,
        ]);

        $this->actAsAdmin();

        $this->assertActionRejected(
            fn () => Volt::test('catalog.addons')->call('deleteGroup', $foreignGroup->id)
        );

        $this->assertActionRejected(
            fn () => Volt::test('catalog.addons')->call('deleteOption', $foreignOption->id)
        );

        // Creating an option under a foreign group must also be fenced.
        $this->assertActionRejected(
            fn () => Volt::test('catalog.addons')
                ->set('optionGroupId', (string) $foreignGroup->id)
                ->set('optionName', 'Bacon')
                ->set('optionPrice', '4.00')
                ->call('saveOption'),
            'saveOption must not attach an option to a foreign group.'
        );

        $this->assertDatabaseHas('addon_groups', ['id' => $foreignGroup->id]);
        $this->assertDatabaseHas('addon_options', ['id' => $foreignOption->id]);
        $this->assertDatabaseMissing('addon_options', ['name' => 'Bacon']);
    }

    public function test_product_form_rejects_foreign_category_assignment(): void
    {
        [$panel, $foreign] = $this->panelAndForeign();
        $this->category($panel); // the panel has its own category available
        $foreignCategory = $this->category($foreign);

        $this->actAsAdmin();

        Livewire::test(\App\Livewire\Catalog\ProductForm::class)
            ->set('name', 'X-Burger')
            ->set('price', '25.00')
            ->set('category_id', (string) $foreignCategory->id)
            ->call('save')
            ->assertHasErrors(['category_id']);

        $this->assertDatabaseMissing('products', ['name' => 'X-Burger']);
    }
}
