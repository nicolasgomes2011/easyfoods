<?php

namespace Tests\Feature\Store;

use App\Actions\Orders\PlaceOrder;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * The payment method picked at checkout must reach the order as a pending
 * Payment (EF-4), and only methods enabled in Settings › Payments are accepted.
 */
class CheckoutPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create([
            'accepts_delivery' => true,
            'accepts_pickup'   => true,
            'accepts_dine_in'  => true,
        ]);

        $category = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name'          => 'Lanches',
            'slug'          => 'lanches',
            'is_active'     => true,
            'sort_order'    => 1,
        ]);

        $this->product = Product::create([
            'restaurant_id'       => $this->restaurant->id,
            'category_id'         => $category->id,
            'name'                => 'X-Burger',
            'price'               => 25.00,
            'availability_status' => 'available',
            'sort_order'          => 1,
            'is_featured'         => false,
        ]);
    }

    private function addToCart(int $qty = 1): void
    {
        Volt::test('store.menu')
            ->call('openProduct', $this->product->id)
            ->set('qty', $qty)
            ->call('addToCart', $this->product->id)
            ->assertHasNoErrors();
    }

    /** Enable exactly these settings keys (e.g. payment_pix), disabling the rest. */
    private function enablePayments(array $keys): void
    {
        $all = ['payment_cash', 'payment_pix', 'payment_credit_card', 'payment_debit_card', 'payment_meal_voucher'];

        foreach ($all as $key) {
            StoreSetting::create([
                'restaurant_id' => $this->restaurant->id,
                'key'           => $key,
                'value'         => in_array($key, $keys, true) ? '1' : '0',
                'type'          => 'boolean',
            ]);
        }
    }

    private function pickupCheckout(string $method)
    {
        return Volt::test('store.checkout')
            ->set('deliveryType', 'pickup')
            ->set('customerName', 'Maria')
            ->set('paymentMethod', $method);
    }

    public function test_checkout_creates_one_pending_payment_with_the_chosen_method(): void
    {
        $this->enablePayments(['payment_pix', 'payment_cash']);
        $this->addToCart(2);

        $this->pickupCheckout('pix')
            ->call('placeOrder')
            ->assertHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertSame(1, $order->payments()->count());

        $payment = $order->payment;
        $this->assertSame(PaymentMethod::Pix, $payment->method);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(50.0, (float) $payment->amount);
        $this->assertNull($payment->paid_at);
        $this->assertNull($payment->amount_tendered);
    }

    public function test_cash_payment_keeps_the_change_the_customer_asked_for(): void
    {
        $this->addToCart(2); // total 50,00

        $this->pickupCheckout('cash')
            ->set('cashChangeFor', '100,00')
            ->call('placeOrder')
            ->assertHasNoErrors();

        $payment = Order::firstOrFail()->payment;
        $this->assertSame(PaymentMethod::Cash, $payment->method);
        $this->assertSame(100.0, (float) $payment->amount_tendered);
        $this->assertSame(50.0, (float) $payment->change_due);
    }

    public function test_change_below_the_order_total_is_refused(): void
    {
        $this->addToCart(2); // total 50,00

        $this->pickupCheckout('cash')
            ->set('cashChangeFor', '20')
            ->call('placeOrder')
            ->assertHasErrors('cashChangeFor');

        $this->assertSame(0, Order::count());
        $this->assertSame(0, Payment::count());
    }

    public function test_change_is_ignored_when_the_method_is_not_cash(): void
    {
        $this->enablePayments(['payment_cash', 'payment_credit_card']);
        $this->addToCart();

        $this->pickupCheckout('credit_card')
            ->set('cashChangeFor', '100')
            ->call('placeOrder')
            ->assertHasNoErrors();

        $payment = Order::firstOrFail()->payment;
        $this->assertSame(PaymentMethod::CreditCard, $payment->method);
        $this->assertNull($payment->amount_tendered);
        $this->assertNull($payment->change_due);
    }

    public function test_method_disabled_in_settings_is_refused_at_checkout(): void
    {
        $this->enablePayments(['payment_pix']);
        $this->addToCart();

        $this->pickupCheckout('cash')
            ->call('placeOrder')
            ->assertHasErrors('paymentMethod');

        $this->assertSame(0, Order::count());
    }

    public function test_meal_voucher_enabled_in_settings_is_accepted(): void
    {
        $this->enablePayments(['payment_meal_voucher']);
        $this->addToCart();

        $this->pickupCheckout(PaymentMethod::Voucher->value)
            ->call('placeOrder')
            ->assertHasNoErrors();

        $this->assertSame(PaymentMethod::Voucher, Order::firstOrFail()->payment->method);
    }

    public function test_place_order_refuses_a_disabled_method_on_the_server(): void
    {
        $this->enablePayments(['payment_pix']);
        $this->addToCart();

        try {
            app(PlaceOrder::class)->handle(Cart::firstOrFail(), [
                'delivery_type'  => 'pickup',
                'customer_name'  => 'Maria',
                'payment_method' => 'cash',
            ]);
            $this->fail('PlaceOrder should refuse a payment method that is not enabled.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('forma de pagamento', mb_strtolower($e->getMessage()));
        }

        $this->assertSame(0, Order::count());
        $this->assertSame(0, Payment::count());
        $this->assertSame(1, Cart::count()); // transaction rolled back, cart kept
    }

    public function test_placing_the_order_does_not_change_the_order_status_flow(): void
    {
        $this->addToCart();

        $this->pickupCheckout('cash')->call('placeOrder')->assertHasNoErrors();

        $this->assertSame(OrderStatus::PendingConfirmation, Order::firstOrFail()->status);
    }

    public function test_checkout_page_lists_only_enabled_methods_and_the_change_field(): void
    {
        $this->enablePayments(['payment_cash', 'payment_pix']);
        $this->addToCart();

        // Reuse the session that owns the cart so the real route renders it.
        $this->withCookie(config('session.cookie'), session()->getId())
            ->get(route('store.checkout'))
            ->assertOk()
            ->assertSee('Dinheiro')
            ->assertSee('Pix')
            ->assertDontSee('Cartão de crédito')
            ->assertSee('Troco para quanto?')
            ->assertDontSee('Undefined');
    }
}
