<?php

namespace Tests\Feature\Catalog;

use App\Enums\IngredientCategory;
use App\Enums\IngredientUnit;
use App\Enums\UserRole;
use App\Models\Ingredient;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Ingredients (insumos) registry (EF-39): list, create, edit, archive and
 * restore, scoped to the panel restaurant. Admin and manager only.
 */
class IngredientsTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        // Lowest id: the restaurant the panel scopes to (CurrentRestaurant::id()).
        $this->restaurant = Restaurant::factory()->create();
    }

    private function actAs(UserRole $role = UserRole::Manager): static
    {
        return $this->actingAs(User::factory()->create(['role' => $role]));
    }

    private function ingredient(array $attributes = [], ?Restaurant $restaurant = null): Ingredient
    {
        return Ingredient::factory()->for($restaurant ?? $this->restaurant)->create($attributes);
    }

    public function test_admin_and_manager_open_the_page_on_the_real_route(): void
    {
        foreach ([UserRole::Admin, UserRole::Manager] as $role) {
            $this->actAs($role)
                ->get(route('admin.catalog.ingredients'))
                ->assertOk()
                ->assertDontSee('Undefined')
                ->assertSee('Insumos')
                ->assertSee('Nenhum insumo cadastrado ainda.');
        }
    }

    public function test_kitchen_and_other_staff_cannot_open_the_page(): void
    {
        foreach ([UserRole::Kitchen, UserRole::Attendant, UserRole::Delivery] as $role) {
            $this->actAs($role)
                ->get(route('admin.catalog.ingredients'))
                ->assertForbidden();
        }
    }

    public function test_creates_an_ingredient_and_lists_it(): void
    {
        $this->actAs();

        Volt::test('catalog.ingredients')
            ->call('openCreate')
            ->set('name', '  Queijo muçarela ')
            ->set('unit', IngredientUnit::Kilogram->value)
            ->set('category', IngredientCategory::Dairy->value)
            ->set('cost', '45.90')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $ingredient = Ingredient::sole();
        $this->assertSame($this->restaurant->id, $ingredient->restaurant_id);
        $this->assertSame('Queijo muçarela', $ingredient->name);
        $this->assertSame(IngredientUnit::Kilogram, $ingredient->unit);
        $this->assertSame(IngredientCategory::Dairy, $ingredient->category);
        $this->assertSame('45.9000', $ingredient->cost_per_unit);
        $this->assertNull($ingredient->archived_at);

        $this->get(route('admin.catalog.ingredients'))
            ->assertOk()
            ->assertSee('Queijo muçarela')
            ->assertSee('Laticínios')
            ->assertSee('R$ 45,90 / kg');
    }

    public function test_costs_below_a_cent_keep_their_decimals(): void
    {
        $this->assertSame('0,045', $this->ingredient(['cost_per_unit' => 0.045])->formattedCost());
        $this->assertSame('1.250,00', $this->ingredient(['cost_per_unit' => 1250])->formattedCost());
        $this->assertSame('3,1234', $this->ingredient(['cost_per_unit' => 3.1234])->formattedCost());
    }

    public function test_validation_rejects_bad_input(): void
    {
        $this->actAs();

        Volt::test('catalog.ingredients')
            ->call('openCreate')
            ->set('name', '')
            ->set('unit', 'ton')
            ->set('category', 'frozen')
            ->set('cost', '-1')
            ->call('save')
            ->assertHasErrors(['name', 'unit', 'category', 'cost']);

        $this->assertDatabaseCount('ingredients', 0);
    }

    public function test_name_is_unique_per_restaurant(): void
    {
        $this->ingredient(['name' => 'Tomate']);
        $this->ingredient(['name' => 'Cebola'], Restaurant::factory()->create());
        $this->actAs();

        Volt::test('catalog.ingredients')
            ->call('openCreate')
            ->set('name', 'Tomate')
            ->set('cost', '8')
            ->call('save')
            ->assertHasErrors(['name']);

        // Another restaurant's ingredient does not block the name here.
        Volt::test('catalog.ingredients')
            ->call('openCreate')
            ->set('name', 'Cebola')
            ->set('cost', '6')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, Ingredient::where('restaurant_id', $this->restaurant->id)->count());
    }

    public function test_edits_an_ingredient(): void
    {
        $ingredient = $this->ingredient([
            'name'          => 'Farinha',
            'unit'          => IngredientUnit::Kilogram,
            'category'      => IngredientCategory::Other,
            'cost_per_unit' => 4.5,
        ]);
        $this->actAs();

        Volt::test('catalog.ingredients')
            ->call('openEdit', $ingredient->id)
            ->assertSet('showForm', true)
            ->assertSet('name', 'Farinha')
            ->assertSet('unit', 'kg')
            ->assertSet('category', 'other')
            ->assertSet('cost', '4.5')
            ->set('name', 'Farinha de trigo')
            ->set('unit', IngredientUnit::Gram->value)
            ->set('category', IngredientCategory::Bakery->value)
            ->set('cost', '0.0045')
            ->call('save')
            ->assertHasNoErrors();

        $ingredient->refresh();
        $this->assertSame('Farinha de trigo', $ingredient->name);
        $this->assertSame(IngredientUnit::Gram, $ingredient->unit);
        $this->assertSame(IngredientCategory::Bakery, $ingredient->category);
        $this->assertSame('0.0045', $ingredient->cost_per_unit);

        // Keeping its own name is not a duplicate.
        Volt::test('catalog.ingredients')
            ->call('openEdit', $ingredient->id)
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_archives_and_restores_an_ingredient(): void
    {
        $ingredient = $this->ingredient(['name' => 'Alface']);
        $this->actAs();

        $page = Volt::test('catalog.ingredients')
            ->assertSee('Alface')
            ->call('archive', $ingredient->id)
            ->assertDontSee('Alface');

        $this->assertNotNull($ingredient->fresh()->archived_at);

        $page->set('showArchived', true)
            ->assertSee('Alface')
            ->assertSee('Arquivado')
            ->call('restore', $ingredient->id);

        $this->assertNull($ingredient->fresh()->archived_at);
        $this->assertDatabaseCount('ingredients', 1);
    }

    public function test_lists_only_the_panel_restaurant_ingredients(): void
    {
        $this->ingredient(['name' => 'Bacon da casa']);
        $this->ingredient(['name' => 'Bacon do vizinho'], Restaurant::factory()->create());

        $this->actAs()
            ->get(route('admin.catalog.ingredients'))
            ->assertOk()
            ->assertSee('Bacon da casa')
            ->assertDontSee('Bacon do vizinho');
    }

    public function test_actions_on_another_restaurant_ingredient_are_404(): void
    {
        $foreign = $this->ingredient(['name' => 'Do vizinho', 'cost_per_unit' => 10], Restaurant::factory()->create());
        $this->actAs();

        Volt::test('catalog.ingredients')->call('openEdit', $foreign->id)->assertStatus(404);
        Volt::test('catalog.ingredients')->call('archive', $foreign->id)->assertStatus(404);
        Volt::test('catalog.ingredients')->call('restore', $foreign->id)->assertStatus(404);
        Volt::test('catalog.ingredients')
            ->set('editingId', (string) $foreign->id)
            ->set('name', 'Hacked')
            ->set('cost', '1')
            ->call('save')
            ->assertStatus(404);

        $foreign->refresh();
        $this->assertSame('Do vizinho', $foreign->name);
        $this->assertNull($foreign->archived_at);
    }
}
