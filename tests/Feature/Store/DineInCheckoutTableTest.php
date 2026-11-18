<?php

namespace Tests\Feature\Store;

use App\Enums\DiningTableStatus;
use App\Enums\TableSessionStatus;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\TableSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Dine-in table choice at checkout: a table that already has an open tab
 * (occupied) must stay selectable so the same table can order again and join
 * that tab. Reserved tables and tables of another restaurant are never accepted.
 */
class DineInCheckoutTableTest extends TestCase
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

    private function table(Restaurant $restaurant, int $number, DiningTableStatus $status = DiningTableStatus::Free): DiningTable
    {
        return DiningTable::create([
            'restaurant_id' => $restaurant->id,
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

    private function checkoutAtTable(string $tableId)
    {
        return Volt::test('store.checkout')
            ->set('deliveryType', 'dine_in')
            ->set('customerName', 'Carlos')
            ->set('paymentMethod', 'cash')
            ->set('tableId', $tableId)
            ->call('placeOrder');
    }

    public function test_two_dine_in_orders_at_the_same_table_join_the_same_tab(): void
    {
        $table = $this->table($this->restaurant, 7);

        $this->addProductToCart();
        $this->checkoutAtTable((string) $table->id)->assertHasNoErrors();

        // After the first order the table is occupied — it must still be offered.
        $this->assertSame(DiningTableStatus::Occupied, $table->fresh()->status);

        $this->addProductToCart();
        $this->assertTrue(Volt::test('store.checkout')->instance()->tables->contains('id', $table->id));

        $this->checkoutAtTable((string) $table->id)->assertHasNoErrors();

        $orders = Order::where('dining_table_id', $table->id)->get();
        $this->assertCount(2, $orders);
        $this->assertNotNull($orders[0]->table_session_id);
        $this->assertSame($orders[0]->table_session_id, $orders[1]->table_session_id);

        $this->assertSame(1, TableSession::where('dining_table_id', $table->id)->count());
        $this->assertSame(TableSessionStatus::Open, TableSession::first()->status);

        // The table stays occupied until staff close the tab.
        $this->assertSame(DiningTableStatus::Occupied, $table->fresh()->status);
    }

    public function test_checkout_lists_free_and_occupied_tables_but_not_reserved_ones(): void
    {
        $free     = $this->table($this->restaurant, 1);
        $occupied = $this->table($this->restaurant, 2, DiningTableStatus::Occupied);
        $reserved = $this->table($this->restaurant, 3, DiningTableStatus::Reserved);

        $other   = Restaurant::factory()->create();
        $foreign = $this->table($other, 4);

        $this->addProductToCart();

        $tables = Volt::test('store.checkout')->instance()->tables;

        $this->assertTrue($tables->contains('id', $free->id));
        $this->assertTrue($tables->contains('id', $occupied->id));
        $this->assertFalse($tables->contains('id', $reserved->id));
        $this->assertFalse($tables->contains('id', $foreign->id));
    }

    public function test_dine_in_checkout_renders_occupied_tables_and_the_refusal_message(): void
    {
        $occupied = $this->table($this->restaurant, 2, DiningTableStatus::Occupied);
        $reserved = $this->table($this->restaurant, 3, DiningTableStatus::Reserved);

        $this->addProductToCart();

        $this->checkoutAtTable((string) $reserved->id)
            ->assertHasErrors('tableId')
            ->assertSee('Selecione sua mesa')
            ->assertSeeHtml('wire:model="tableId" value="'.$occupied->id.'"')
            ->assertDontSeeHtml('wire:model="tableId" value="'.$reserved->id.'"')
            ->assertSee('Essa mesa não está disponível. Escolha outra.');
    }

    public function test_reserved_table_is_refused(): void
    {
        $reserved = $this->table($this->restaurant, 3, DiningTableStatus::Reserved);

        $this->addProductToCart();
        $this->checkoutAtTable((string) $reserved->id)->assertHasErrors('tableId');

        $this->assertSame(0, Order::count());
        $this->assertSame(DiningTableStatus::Reserved, $reserved->fresh()->status);
    }

    public function test_table_of_another_restaurant_is_refused(): void
    {
        $other   = Restaurant::factory()->create();
        $foreign = $this->table($other, 9);

        $this->addProductToCart();
        $this->checkoutAtTable((string) $foreign->id)->assertHasErrors('tableId');

        $this->assertSame(0, Order::count());
        $this->assertSame(DiningTableStatus::Free, $foreign->fresh()->status);
        $this->assertSame(0, TableSession::count());
    }
}
