<?php

namespace Tests\Feature\Catalog;

use App\Enums\ProductAvailabilityStatus;
use App\Enums\UserRole;
use App\Models\AddonGroup;
use App\Models\AddonOption;
use App\Models\Category;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AddonManagementTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $restaurant = Restaurant::factory()->create();
        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Lanches',
            'slug'          => 'lanches',
            'is_active'     => true,
            'sort_order'    => 1,
        ]);
        $this->product = Product::create([
            'restaurant_id'       => $restaurant->id,
            'category_id'         => $category->id,
            'name'                => 'X-Salada',
            'price'               => 20.00,
            'availability_status' => ProductAvailabilityStatus::Available,
            'sort_order'          => 0,
            'is_featured'         => false,
        ]);

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    public function test_addons_page_renders(): void
    {
        $this->get(route('admin.catalog.addons'))
            ->assertOk()
            ->assertSee('Complementos');
    }

    public function test_group_form_creates_and_edits_a_group(): void
    {
        Volt::test('catalog.addons')
            ->call('openCreateGroup')
            ->assertSee('Novo grupo de complementos')
            ->assertSee('X-Salada')
            ->call('saveGroup')
            ->assertHasErrors(['groupName', 'groupProductId'])
            ->assertSeeHtml('<p class="text-red-400 text-xs mt-1">')
            ->set('groupProductId', (string) $this->product->id)
            ->set('groupName', 'Ponto da carne')
            ->call('saveGroup')
            ->assertHasNoErrors()
            ->assertSet('showGroupForm', false)
            ->assertSee('Ponto da carne');

        $group = AddonGroup::firstOrFail();

        Volt::test('catalog.addons')
            ->call('openEditGroup', $group->id)
            ->assertSee('Editar grupo')
            ->assertSet('groupName', 'Ponto da carne');
    }

    public function test_option_form_adds_and_edits_an_option(): void
    {
        $group = AddonGroup::create([
            'product_id'  => $this->product->id,
            'name'        => 'Adicionais',
            'required'    => false,
            'min_choices' => 0,
            'max_choices' => 2,
            'sort_order'  => 1,
            'is_active'   => true,
        ]);

        Volt::test('catalog.addons')
            ->call('openAddOption', $group->id)
            ->assertSee('Nova opção')
            ->set('optionName', 'Bacon')
            ->set('optionPrice', '4.00')
            ->call('saveOption')
            ->assertHasNoErrors()
            ->assertSee('Bacon (+R$ 4,00)');

        $option = AddonOption::firstOrFail();

        Volt::test('catalog.addons')
            ->call('openEditOption', $option->id)
            ->assertSee('Editar opção')
            ->assertSet('optionPrice', '4.00');
    }
}
