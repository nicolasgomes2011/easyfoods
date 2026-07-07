<?php

namespace Tests\Feature\Catalog;

use App\Actions\Orders\PlaceOrder;
use App\Enums\OrderStatus;
use App\Enums\ProductAvailabilityStatus;
use App\Enums\UserRole;
use App\Livewire\Catalog\ProductList;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Archive vs delete lifecycle: archived products vanish from panel + storefront
 * but keep history; delete is blocked only by orders still in play (closed
 * orders keep frozen snapshots, so a hard delete is safe by design).
 */
class ProductLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private Category $category;
    private Product $product;

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

        $this->product = Product::create([
            'restaurant_id'       => $this->restaurant->id,
            'category_id'         => $this->category->id,
            'name'                => 'X-Salada Especial',
            'price'               => 25.00,
            'availability_status' => ProductAvailabilityStatus::Available,
            'sort_order'          => 1,
            'is_featured'         => false,
        ]);
    }

    private function actAsAdmin(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    private function orderWithProduct(OrderStatus $status): Order
    {
        $order = Order::factory()->for($this->restaurant)->create(['status' => $status]);

        OrderItem::create([
            'order_id'     => $order->id,
            'product_id'   => $this->product->id,
            'product_name' => $this->product->name,
            'unit_price'   => 25.00,
            'quantity'     => 1,
            'subtotal'     => 25.00,
        ]);

        return $order;
    }

    public function test_archive_hides_the_product_from_the_default_list(): void
    {
        $this->actAsAdmin();

        Livewire::test(ProductList::class)
            ->call('archive', $this->product->id)
            ->assertHasNoErrors();

        $this->assertNotNull($this->product->fresh()->archived_at);

        Livewire::test(ProductList::class)
            ->assertDontSee('X-Salada Especial');

        Livewire::test(ProductList::class)
            ->set('showArchived', true)
            ->assertSee('X-Salada Especial')
            ->assertSee('Restaurar');
    }

    public function test_unarchive_puts_the_product_back(): void
    {
        $this->product->update(['archived_at' => now()]);
        $this->actAsAdmin();

        Livewire::test(ProductList::class)
            ->call('unarchive', $this->product->id)
            ->assertHasNoErrors();

        $this->assertNull($this->product->fresh()->archived_at);

        Livewire::test(ProductList::class)->assertSee('X-Salada Especial');
    }

    public function test_archived_product_disappears_from_the_storefront_menu(): void
    {
        // Baseline: visible on the real route.
        $this->get(route('store.menu'))->assertOk()->assertSee('X-Salada Especial');

        $this->product->update(['archived_at' => now()]);

        $response = $this->get(route('store.menu'));
        $response->assertOk()->assertDontSee('X-Salada Especial');
        // The category held only this product, so its tab goes away too.
        $response->assertDontSee('Lanches');
    }

    public function test_add_to_cart_is_blocked_for_archived_products(): void
    {
        $this->product->update(['archived_at' => now()]);

        Volt::test('store.menu')
            ->call('openProduct', $this->product->id)
            ->set('qty', 1)
            ->call('addToCart', $this->product->id);

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_delete_is_blocked_while_an_open_order_references_the_product(): void
    {
        $this->orderWithProduct(OrderStatus::InPreparation);
        $this->actAsAdmin();

        Livewire::test(ProductList::class)
            ->call('delete', $this->product->id)
            ->assertHasErrors(['delete']);

        $this->assertDatabaseHas('products', ['id' => $this->product->id]);
    }

    public function test_delete_succeeds_when_only_closed_orders_reference_the_product(): void
    {
        $order = $this->orderWithProduct(OrderStatus::Completed);
        $this->actAsAdmin();

        Livewire::test(ProductList::class)
            ->call('delete', $this->product->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('products', ['id' => $this->product->id]);

        // Frozen snapshot survives: name/price intact, FK nulled out.
        $item = OrderItem::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('X-Salada Especial', $item->product_name);
        $this->assertNull($item->product_id);
    }

    public function test_place_order_rejects_an_archived_product_left_in_a_cart(): void
    {
        $cart = Cart::create([
            'session_id'    => 'test-session',
            'restaurant_id' => $this->restaurant->id,
        ]);

        CartItem::create([
            'cart_id'    => $cart->id,
            'product_id' => $this->product->id,
            'quantity'   => 1,
        ]);

        // Archived AFTER it went into the cart — checkout must still refuse it.
        $this->product->update(['archived_at' => now()]);

        try {
            app(PlaceOrder::class)->handle($cart, [
                'delivery_type' => 'pickup',
                'customer_name' => 'Cliente Teste',
            ]);
            $this->fail('PlaceOrder should reject an archived product.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('não está mais disponível', $e->getMessage());
        }

        $this->assertDatabaseCount('orders', 0);
    }
}
