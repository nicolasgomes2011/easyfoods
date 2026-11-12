<?php

namespace Tests\Feature\Store;

use App\Enums\DeliveryType;
use App\Enums\DiningTableStatus;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Table QR code (/mesa/{uuid}): the scanned table is bound to the browser
 * session, the menu shows it, and checkout places a dine-in order for that
 * table only. ?kiosk=1 (tablet fixed on the table) also hides delivery/pickup.
 */
class TableModeTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private Product $product;
    private DiningTable $table;

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

        $this->table = $this->table(7);
    }

    private function table(int $number, DiningTableStatus $status = DiningTableStatus::Free): DiningTable
    {
        return DiningTable::create([
            'restaurant_id' => $this->restaurant->id,
            'number'        => $number,
            'status'        => $status,
        ]);
    }

    private function addProductToCart(): void
    {
        Volt::test('store.menu')
            ->call('openProduct', $this->product->id)
            ->set('qty', 1)
            ->call('addToCart', $this->product->id)
            ->assertHasNoErrors();
    }

    private function placeTableOrder(string $name = 'Ana'): void
    {
        $this->addProductToCart();

        Volt::test('store.checkout')
            ->set('customerName', $name)
            ->set('paymentMethod', 'cash')
            ->call('placeOrder')
            ->assertHasNoErrors()
            ->assertSet('error', null);
    }

    public function test_table_link_binds_the_table_and_opens_the_menu_in_table_mode(): void
    {
        $this->get('/mesa/'.$this->table->uuid)
            ->assertRedirect(route('store.menu'))
            ->assertSessionHas('table_mode.table_id', $this->table->id);

        $this->get(route('store.menu'))
            ->assertOk()
            ->assertSee('Mesa 7')
            ->assertDontSee('Retirada');
    }

    public function test_unknown_table_uuid_is_not_found(): void
    {
        $this->get('/mesa/'.Str::uuid())->assertNotFound();
        $this->get('/mesa/nao-existe')->assertNotFound();
    }

    public function test_order_from_table_mode_goes_to_the_scanned_table(): void
    {
        $this->get('/mesa/'.$this->table->uuid);
        $this->addProductToCart();

        // Real checkout URL, same browser session as the cart.
        $this->withCookie(config('session.cookie'), session()->getId())
            ->get(route('store.checkout'))
            ->assertOk()
            ->assertSee('Mesa 7')
            ->assertDontSee('Selecione sua mesa');

        Volt::test('store.checkout')
            ->assertSet('deliveryType', 'dine_in')
            ->assertSet('tableId', (string) $this->table->id)
            ->assertSee('Mesa 7')
            ->set('customerName', 'Ana') // phone is optional at the table
            ->set('paymentMethod', 'cash')
            ->call('placeOrder')
            ->assertHasNoErrors()
            ->assertSet('error', null);

        $order = Order::sole();
        $this->assertSame(DeliveryType::DineIn, $order->delivery_type);
        $this->assertSame($this->table->id, $order->dining_table_id);
        $this->assertSame(7, (int) $order->table_number);
        $this->assertNull($order->customer_phone);
        $this->assertNotNull($order->table_session_id);
    }

    public function test_two_orders_from_the_same_table_share_the_table_session(): void
    {
        $this->get('/mesa/'.$this->table->uuid);

        $this->placeTableOrder('Ana');
        $this->placeTableOrder('Bruno'); // table is occupied now — it joins the open tab

        $orders = Order::orderBy('id')->get();
        $this->assertCount(2, $orders);
        $this->assertNotNull($orders[0]->table_session_id);
        $this->assertSame($orders[0]->table_session_id, $orders[1]->table_session_id);
        $this->assertSame([$this->table->id, $this->table->id], $orders->pluck('dining_table_id')->all());
    }

    public function test_kiosk_hides_delivery_and_pickup(): void
    {
        $this->get('/mesa/'.$this->table->uuid.'?kiosk=1')
            ->assertRedirect(route('store.menu'))
            ->assertSessionHas('table_mode.kiosk', true);

        $this->get(route('store.menu'))
            ->assertOk()
            ->assertSee('Mesa 7')
            ->assertDontSee('Delivery')
            ->assertDontSee('Retirada');

        $this->addProductToCart();

        Volt::test('store.checkout')
            ->assertSee('Mesa 7')
            ->assertDontSee('Delivery')
            ->assertDontSee('Retirada')
            ->assertDontSee('Pedir para entrega');
    }

    public function test_table_mode_ignores_a_tampered_type_or_table(): void
    {
        $other = $this->table(8);

        $this->get('/mesa/'.$this->table->uuid);
        $this->addProductToCart();

        Volt::test('store.checkout')
            ->set('deliveryType', 'delivery')
            ->assertSet('deliveryType', 'dine_in')
            ->set('tableId', (string) $other->id)
            ->assertSet('tableId', (string) $this->table->id)
            ->set('customerName', 'Ana')
            ->set('paymentMethod', 'cash')
            ->call('placeOrder')
            ->assertHasNoErrors();

        $order = Order::sole();
        $this->assertSame(DeliveryType::DineIn, $order->delivery_type);
        $this->assertSame($this->table->id, $order->dining_table_id);
    }

    public function test_reserved_table_shows_a_clear_message(): void
    {
        $reserved = $this->table(9, DiningTableStatus::Reserved);

        $this->get('/mesa/'.$reserved->uuid)
            ->assertOk()
            ->assertSee('A mesa 9 está reservada')
            ->assertSessionMissing('table_mode');
    }

    public function test_restaurant_without_dine_in_shows_a_clear_message(): void
    {
        $this->restaurant->update(['accepts_dine_in' => false]);

        $this->get('/mesa/'.$this->table->uuid)
            ->assertOk()
            ->assertSee('não está aceitando pedidos na mesa')
            ->assertSessionMissing('table_mode');
    }

    public function test_table_reserved_after_scanning_blocks_the_order(): void
    {
        $this->get('/mesa/'.$this->table->uuid);
        $this->addProductToCart();
        $this->table->update(['status' => DiningTableStatus::Reserved]);

        Volt::test('store.checkout')
            ->set('customerName', 'Ana')
            ->set('paymentMethod', 'cash')
            ->call('placeOrder')
            ->assertSet('error', 'A mesa 7 está reservada no momento. Chame um atendente para fazer o pedido.');

        $this->assertSame(0, Order::count());
    }

    public function test_customer_can_leave_table_mode_outside_the_kiosk(): void
    {
        $this->get('/mesa/'.$this->table->uuid);
        $this->addProductToCart();

        Volt::test('store.checkout')
            ->assertSee('Pedir para entrega ou retirada')
            ->call('leaveTableMode')
            ->assertSee('Retirada');

        $this->assertNull(session('table_mode'));
    }

    public function test_kiosk_cannot_leave_table_mode(): void
    {
        $this->get('/mesa/'.$this->table->uuid.'?kiosk=1');
        $this->addProductToCart();

        Volt::test('store.checkout')
            ->call('leaveTableMode')
            ->assertSet('deliveryType', 'dine_in')
            ->assertDontSee('Retirada');

        $this->assertSame($this->table->id, session('table_mode.table_id'));
    }
}
