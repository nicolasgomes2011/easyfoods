<?php

namespace Tests\Feature\Order;

use App\Actions\Orders\PlaceOrder;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Order numbers come from an integer per-restaurant sequence, so the next
 * number never depends on a database-specific CAST or on string ordering.
 */
class OrderNumberingTest extends TestCase
{
    use RefreshDatabase;

    private function placeOrder(Restaurant $restaurant): Order
    {
        $category = Category::firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'slug' => 'lanches'],
            ['name' => 'Lanches', 'is_active' => true, 'sort_order' => 1],
        );

        $product = Product::firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'name' => 'X-Burger'],
            [
                'category_id'         => $category->id,
                'price'               => 25.00,
                'availability_status' => 'available',
                'sort_order'          => 1,
                'is_featured'         => false,
            ],
        );

        $cart = Cart::create([
            'session_id'    => 'numbering-' . uniqid(),
            'restaurant_id' => $restaurant->id,
            'delivery_type' => 'pickup',
        ]);

        CartItem::create([
            'cart_id'    => $cart->id,
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        return app(PlaceOrder::class)->handle($cart, [
            'delivery_type' => 'pickup',
            'customer_name' => 'Cliente Teste',
        ]);
    }

    public function test_consecutive_orders_are_numbered_00001_00002_00003(): void
    {
        $restaurant = Restaurant::factory()->create(['accepts_pickup' => true]);

        $numbers = collect(range(1, 3))->map(fn () => $this->placeOrder($restaurant));

        $this->assertSame(['00001', '00002', '00003'], $numbers->pluck('number')->all());
        $this->assertSame([1, 2, 3], $numbers->map(fn (Order $o) => $o->fresh()->sequence)->all());
    }

    public function test_each_restaurant_has_its_own_sequence(): void
    {
        $first = Restaurant::factory()->create(['accepts_pickup' => true]);
        $second = Restaurant::factory()->create(['accepts_pickup' => true]);

        $this->placeOrder($first);
        $this->placeOrder($first);
        $other = $this->placeOrder($second);

        $this->assertSame('00001', $other->number);
        $this->assertSame('00003', $this->placeOrder($first)->number);
    }

    public function test_numbering_continues_past_five_digits(): void
    {
        $restaurant = Restaurant::factory()->create(['accepts_pickup' => true]);
        Order::factory()->for($restaurant)->create(['number' => '99999', 'sequence' => 99999]);

        $this->assertSame('100000', $this->placeOrder($restaurant)->number);
    }
}
