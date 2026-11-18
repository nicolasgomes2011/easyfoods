<?php

namespace Tests\Feature\Store;

use App\Exceptions\OrderNotPlaceable;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * The delivery address of the checkout is frozen on the order, so its fields
 * must fit the orders columns: a too long value is a validation error on the
 * field (SQLite does not enforce the length, MySQL strict refuses the insert
 * and the customer would only see the generic message).
 */
class CheckoutAddressLengthTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $restaurant = Restaurant::factory()->create(['accepts_delivery' => true]);

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
            'name'                => 'X-Burger',
            'price'               => 25.00,
            'availability_status' => 'available',
            'sort_order'          => 1,
            'is_featured'         => false,
        ]);

        Volt::test('store.menu')
            ->call('openProduct', $this->product->id)
            ->call('addToCart', $this->product->id)
            ->assertHasNoErrors();
    }

    private function deliveryCheckout(array $address)
    {
        $component = Volt::test('store.checkout')
            ->set('deliveryType', 'delivery')
            ->set('customerName', 'Joao Silva')
            ->set('street', 'Rua das Flores')
            ->set('addressNumber', '100')
            ->set('paymentMethod', 'cash');

        foreach ($address as $field => $value) {
            $component->set($field, $value);
        }

        return $component->call('placeOrder');
    }

    private function assertRefusedOnField($component, string $field): void
    {
        $component->assertHasErrors([$field => 'max'])
            ->assertSet('error', null) // a field error, never the generic message
            ->assertDontSee(OrderNotPlaceable::GENERIC_MESSAGE);

        $this->assertSame(0, Order::count());
    }

    public function test_zip_with_ten_characters_is_a_field_error(): void
    {
        $this->assertRefusedOnField($this->deliveryCheckout(['zip' => '01.234-567']), 'zip');
    }

    public function test_zip_with_nine_characters_is_stored(): void
    {
        $this->deliveryCheckout(['zip' => '01234-567'])->assertHasNoErrors();

        $this->assertSame('01234-567', Order::firstOrFail()->delivery_address_zip);
    }

    public function test_complement_above_the_limit_is_a_field_error(): void
    {
        $this->assertRefusedOnField($this->deliveryCheckout(['complement' => str_repeat('a', 101)]), 'complement');
    }

    public function test_neighborhood_above_the_limit_is_a_field_error(): void
    {
        $this->assertRefusedOnField($this->deliveryCheckout(['neighborhood' => str_repeat('b', 101)]), 'neighborhood');
    }

    public function test_complement_and_neighborhood_at_the_limit_are_stored(): void
    {
        $this->deliveryCheckout([
            'complement'   => str_repeat('a', 100),
            'neighborhood' => str_repeat('b', 100),
        ])->assertHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertSame(100, mb_strlen($order->delivery_address_complement));
        $this->assertSame(100, mb_strlen($order->delivery_address_neighborhood));
    }

    public function test_the_errors_show_on_the_address_form(): void
    {
        $this->deliveryCheckout([
            'zip'          => '01.234-567',
            'complement'   => str_repeat('a', 101),
            'neighborhood' => str_repeat('b', 101),
        ])
            ->assertHasErrors(['zip', 'complement', 'neighborhood'])
            ->assertSee('O campo CEP não pode ter mais de 9 caracteres.')
            ->assertSee('O campo complemento não pode ter mais de 100 caracteres.')
            ->assertSee('O campo bairro não pode ter mais de 100 caracteres.');

        $this->assertSame(0, Order::count());
    }

    public function test_real_checkout_route_shows_the_limits_on_the_inputs(): void
    {
        // Hand the cart to the browser session of the real HTTP request.
        $sessionId = Str::random(40);
        Cart::query()->update(['session_id' => $sessionId]);

        $html = $this->withCookie(config('session.cookie'), $sessionId)
            ->get(route('store.checkout'))
            ->assertOk()
            ->assertDontSee('Undefined')
            ->assertDontSee('placeholder="CEP *"', false)
            ->assertDontSee('placeholder="Cidade *"', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/wire:model="zip"[^>]*maxlength="9"/', $html);
        $this->assertMatchesRegularExpression('/wire:model="complement"[^>]*maxlength="100"/', $html);
        $this->assertMatchesRegularExpression('/wire:model="neighborhood"[^>]*maxlength="100"/', $html);
    }
}
