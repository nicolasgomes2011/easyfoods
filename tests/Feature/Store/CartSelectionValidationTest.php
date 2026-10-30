<?php

namespace Tests\Feature\Store;

use App\Actions\Cart\AddItemToCart;
use App\Actions\Orders\PlaceOrder;
use App\Models\AddonGroup;
use App\Models\AddonOption;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * The variant / addon ids chosen in the menu modal are client-controlled, so
 * the server must only accept ids that belong to the product being added —
 * both when the item enters the cart and again when the order is placed.
 */
class CartSelectionValidationTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private Product $product;
    private ProductVariant $large;
    private AddonGroup $group;
    private AddonOption $bacon;
    private Product $otherProduct;
    private ProductVariant $otherVariant;
    private AddonOption $otherOption;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create(['accepts_pickup' => true]);

        $this->product = $this->makeProduct($this->restaurant, 'Pizza', 40.00);
        $this->large = $this->makeVariant($this->product, 'Grande', 60.00);
        $this->group = $this->makeGroup($this->product, 'Extras', required: false, max: 3);
        $this->bacon = $this->makeOption($this->group, 'Bacon', 4.00);

        // A cheap product of another restaurant, the source of foreign ids.
        $foreign = Restaurant::factory()->create();
        $this->otherProduct = $this->makeProduct($foreign, 'Mini', 5.00);
        $this->otherVariant = $this->makeVariant($this->otherProduct, 'Mini', 5.00);
        $this->otherOption = $this->makeOption(
            $this->makeGroup($this->otherProduct, 'Molhos', required: false, max: 3),
            'Ketchup',
            0.50
        );
    }

    private function makeProduct(Restaurant $restaurant, string $name, float $price): Product
    {
        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Cat ' . $name,
            'slug'          => 'cat-' . strtolower($name),
            'is_active'     => true,
            'sort_order'    => 1,
        ]);

        return Product::create([
            'restaurant_id'       => $restaurant->id,
            'category_id'         => $category->id,
            'name'                => $name,
            'price'               => $price,
            'availability_status' => 'available',
            'sort_order'          => 1,
            'is_featured'         => false,
        ]);
    }

    private function makeVariant(Product $product, string $name, float $price, string $status = 'available'): ProductVariant
    {
        return ProductVariant::create([
            'product_id'          => $product->id,
            'name'                => $name,
            'price'               => $price,
            'availability_status' => $status,
            'sort_order'          => 1,
        ]);
    }

    private function makeGroup(Product $product, string $name, bool $required, int $max): AddonGroup
    {
        return AddonGroup::create([
            'product_id'  => $product->id,
            'name'        => $name,
            'required'    => $required,
            'min_choices' => $required ? 1 : 0,
            'max_choices' => $max,
            'sort_order'  => 1,
            'is_active'   => true,
        ]);
    }

    private function makeOption(AddonGroup $group, string $name, float $price, bool $active = true): AddonOption
    {
        return AddonOption::create([
            'addon_group_id' => $group->id,
            'name'           => $name,
            'price'          => $price,
            'is_active'      => $active,
            'sort_order'     => 1,
        ]);
    }

    private function menu()
    {
        return Volt::test('store.menu')->call('openProduct', $this->product->id);
    }

    private function assertRejected($component): void
    {
        $component->assertSet('modalError', fn ($v) => is_string($v) && $v !== '');
        $this->assertSame(0, CartItem::count());
    }

    private function placePickupOrder(): void
    {
        app(PlaceOrder::class)->handle(Cart::firstOrFail(), [
            'delivery_type' => 'pickup',
            'customer_name' => 'Cliente Teste',
        ]);
    }

    public function test_variant_of_another_product_is_rejected(): void
    {
        $this->assertRejected(
            $this->menu()
                ->set('selectedVariant', $this->otherVariant->id)
                ->call('addToCart', $this->product->id)
        );
    }

    public function test_unavailable_variant_of_the_same_product_is_rejected(): void
    {
        $paused = $this->makeVariant($this->product, 'Família', 80.00, 'unavailable');

        $this->assertRejected(
            $this->menu()
                ->set('selectedVariant', $paused->id)
                ->call('addToCart', $this->product->id)
        );
    }

    public function test_addon_option_of_another_product_is_rejected(): void
    {
        $this->assertRejected(
            $this->menu()
                ->set('selectedVariant', $this->large->id)
                ->set('selectedAddons.' . $this->group->id, [$this->otherOption->id])
                ->call('addToCart', $this->product->id)
        );
    }

    public function test_inactive_addon_option_is_rejected(): void
    {
        $off = $this->makeOption($this->group, 'Cheddar', 6.00, active: false);

        $this->assertRejected(
            $this->menu()
                ->set('selectedVariant', $this->large->id)
                ->set('selectedAddons.' . $this->group->id, [$off->id])
                ->call('addToCart', $this->product->id)
        );
    }

    public function test_nonexistent_addon_option_is_rejected_without_a_server_error(): void
    {
        $this->assertRejected(
            $this->menu()
                ->set('selectedVariant', $this->large->id)
                ->set('selectedAddons.' . $this->group->id, [999999])
                ->call('addToCart', $this->product->id)
        );
    }

    public function test_addon_group_that_is_not_of_the_product_is_rejected(): void
    {
        $foreignGroupId = $this->otherOption->addon_group_id;

        $this->assertRejected(
            $this->menu()
                ->set('selectedVariant', $this->large->id)
                ->set('selectedAddons.' . $foreignGroupId, [$this->otherOption->id])
                ->call('addToCart', $this->product->id)
        );
    }

    public function test_required_group_is_only_satisfied_by_its_own_active_option(): void
    {
        $required = $this->makeGroup($this->product, 'Borda', required: true, max: 1);
        $this->makeOption($required, 'Catupiry', 8.00);

        // An option of a different group of the same product does not count.
        $this->assertRejected(
            $this->menu()
                ->set('selectedVariant', $this->large->id)
                ->set('selectedAddons.' . $required->id, $this->bacon->id)
                ->call('addToCart', $this->product->id)
        );
    }

    public function test_valid_selection_is_still_added_with_the_right_price(): void
    {
        $this->menu()
            ->set('selectedVariant', $this->large->id)
            ->set('selectedAddons.' . $this->group->id, [$this->bacon->id])
            ->call('addToCart', $this->product->id)
            ->assertSet('modalError', null);

        $item = CartItem::with('addons')->firstOrFail();
        $this->assertSame($this->large->id, $item->product_variant_id);
        $this->assertSame(64.0, $item->lineTotal()); // 60 + bacon 4
    }

    public function test_action_refuses_a_foreign_variant_even_without_the_menu(): void
    {
        try {
            app(AddItemToCart::class)->handle(
                $this->restaurant,
                'session-x',
                $this->product,
                $this->otherVariant->id,
                [],
                1,
                null,
            );
            $this->fail('AddItemToCart should reject a variant of another product.');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame(0, Cart::count());
        $this->assertSame(0, CartItem::count());
    }

    public function test_cart_price_never_uses_a_variant_of_another_product(): void
    {
        $item = new CartItem([
            'product_id'         => $this->product->id,
            'product_variant_id' => $this->otherVariant->id,
            'quantity'           => 1,
        ]);

        $this->assertSame(40.0, $item->unitPrice());
    }

    public function test_place_order_rejects_a_variant_that_became_unavailable(): void
    {
        $this->menu()
            ->set('selectedVariant', $this->large->id)
            ->call('addToCart', $this->product->id)
            ->assertSet('modalError', null);

        $this->large->update(['availability_status' => 'out_of_stock']);

        $this->expectExceptionMessage('não está mais disponível');
        try {
            $this->placePickupOrder();
        } finally {
            $this->assertDatabaseCount('orders', 0);
        }
    }

    public function test_place_order_rejects_an_addon_that_was_deactivated(): void
    {
        $this->menu()
            ->set('selectedVariant', $this->large->id)
            ->set('selectedAddons.' . $this->group->id, [$this->bacon->id])
            ->call('addToCart', $this->product->id)
            ->assertSet('modalError', null);

        $this->bacon->update(['is_active' => false]);

        $this->expectExceptionMessage('não está mais disponível');
        try {
            $this->placePickupOrder();
        } finally {
            $this->assertDatabaseCount('orders', 0);
        }
    }

    public function test_place_order_rejects_a_cart_line_with_a_foreign_variant_or_addon(): void
    {
        // A line that bypassed the cart action (e.g. written before this fix).
        $cart = Cart::create([
            'session_id'    => 'legacy',
            'restaurant_id' => $this->restaurant->id,
            'delivery_type' => 'pickup',
        ]);
        $item = CartItem::create([
            'cart_id'            => $cart->id,
            'product_id'         => $this->product->id,
            'product_variant_id' => $this->large->id,
            'quantity'           => 1,
        ]);
        $item->addons()->create(['addon_option_id' => $this->otherOption->id, 'quantity' => 1]);

        $this->expectExceptionMessage('não está mais disponível');
        try {
            $this->placePickupOrder();
        } finally {
            $this->assertDatabaseCount('orders', 0);
        }
    }
}
