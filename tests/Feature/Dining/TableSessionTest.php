<?php

namespace Tests\Feature\Dining;

use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\TransitionOrderStatus;
use App\Enums\DiningTableStatus;
use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Table tab lifecycle (Phase 2 close-out, Tier B): a session opens on the first
 * dine-in order at a table, later dine-in orders at the same table join it, and
 * staff close it manually (stand-in for "payment happened" — no gateway exists
 * yet). Closing is blocked while an order under it is still active, and a table
 * with an open session cannot be deleted.
 */
class TableSessionTest extends TestCase
{
    use RefreshDatabase;

    private function actAsStaff(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    }

    private function table(Restaurant $restaurant, int $number, DiningTableStatus $status = DiningTableStatus::Free): DiningTable
    {
        return DiningTable::create([
            'restaurant_id' => $restaurant->id,
            'number'        => $number,
            'status'        => $status,
        ]);
    }

    private function cartWithItem(Restaurant $restaurant): Cart
    {
        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Lanches '.$restaurant->id,
            'slug'          => 'lanches-'.$restaurant->id.'-'.uniqid(),
            'is_active'     => true,
            'sort_order'    => 1,
        ]);

        $product = Product::create([
            'restaurant_id'       => $restaurant->id,
            'category_id'         => $category->id,
            'name'                => 'X-Burger',
            'price'               => 20.00,
            'availability_status' => 'available',
            'sort_order'          => 1,
            'is_featured'         => false,
        ]);

        $cart = Cart::create([
            'session_id'    => 'session-'.uniqid(),
            'restaurant_id' => $restaurant->id,
        ]);

        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

        return $cart;
    }

    private function placeDineInOrder(Restaurant $restaurant, DiningTable $table)
    {
        return app(PlaceOrder::class)->handle($this->cartWithItem($restaurant), [
            'delivery_type'   => 'dine_in',
            'customer_name'   => 'Mesa '.$table->number,
            'dining_table_id' => $table->id,
            'table_number'    => (string) $table->number,
        ]);
    }

    public function test_tables_page_renders_on_the_real_route_with_an_open_session(): void
    {
        $restaurant = Restaurant::factory()->create();
        $table = $this->table($restaurant, 1);
        $this->placeDineInOrder($restaurant, $table);

        $this->actAsStaff();

        $this->get(route('admin.dining.tables'))
            ->assertOk()
            ->assertSee('Fechar mesa')
            ->assertDontSee('Undefined');
    }

    public function test_first_dine_in_order_opens_a_table_session(): void
    {
        $restaurant = Restaurant::factory()->create();
        $table = $this->table($restaurant, 1);

        $order = $this->placeDineInOrder($restaurant, $table);

        $this->assertNotNull($order->table_session_id);
        $session = TableSession::findOrFail($order->table_session_id);
        $this->assertSame(TableSessionStatus::Open, $session->status);
        $this->assertSame($table->id, $session->dining_table_id);
    }

    public function test_second_dine_in_order_at_the_same_table_reuses_the_open_session(): void
    {
        $restaurant = Restaurant::factory()->create();
        $table = $this->table($restaurant, 2);

        $first = $this->placeDineInOrder($restaurant, $table);
        $second = $this->placeDineInOrder($restaurant, $table);

        $this->assertSame($first->table_session_id, $second->table_session_id);
        $this->assertSame(1, TableSession::where('dining_table_id', $table->id)->count());
    }

    public function test_place_order_ignores_a_foreign_table_and_creates_no_session(): void
    {
        $restaurant = Restaurant::factory()->create();
        $other = Restaurant::factory()->create();
        $foreignTable = $this->table($other, 1);

        $order = app(PlaceOrder::class)->handle($this->cartWithItem($restaurant), [
            'delivery_type'   => 'dine_in',
            'customer_name'   => 'Intruso',
            'dining_table_id' => $foreignTable->id,
            'table_number'    => '1',
        ]);

        $this->assertNull($order->table_session_id);
        $this->assertSame(0, TableSession::count());
    }

    public function test_close_session_is_blocked_while_an_order_is_still_active(): void
    {
        $restaurant = Restaurant::factory()->create();
        $table = $this->table($restaurant, 3);
        $this->placeDineInOrder($restaurant, $table); // lands as pending_confirmation (active)

        $this->actAsStaff();

        Volt::test('dining.tables')->call('closeSession', $table->id)->assertHasErrors(['closeSession']);

        $this->assertSame(TableSessionStatus::Open, $table->openSession->status);
        $this->assertSame(DiningTableStatus::Occupied, $table->fresh()->status);
    }

    public function test_close_session_succeeds_once_all_orders_are_final_and_frees_the_table(): void
    {
        $restaurant = Restaurant::factory()->create();
        $table = $this->table($restaurant, 4);
        $order = $this->placeDineInOrder($restaurant, $table);

        $this->actAsStaff();
        $actor = User::where('role', UserRole::Admin)->firstOrFail();

        $transition = app(TransitionOrderStatus::class);
        $transition->execute($order->fresh(), OrderStatus::Confirmed, $actor);
        $transition->execute($order->fresh(), OrderStatus::InPreparation, $actor);
        $transition->execute($order->fresh(), OrderStatus::ReadyForPickup, $actor);
        $transition->execute($order->fresh(), OrderStatus::Completed, $actor);

        Volt::test('dining.tables')->call('closeSession', $table->id)->assertHasNoErrors();

        $session = TableSession::where('dining_table_id', $table->id)->firstOrFail();
        $this->assertSame(TableSessionStatus::Closed, $session->status);
        $this->assertNotNull($session->closed_at);
        $this->assertSame(DiningTableStatus::Free, $table->fresh()->status);
    }

    public function test_delete_table_is_blocked_while_an_open_session_exists(): void
    {
        $restaurant = Restaurant::factory()->create();
        $table = $this->table($restaurant, 5);
        $this->placeDineInOrder($restaurant, $table);

        $this->actAsStaff();

        Volt::test('dining.tables')->call('delete', $table->id)->assertHasErrors(['delete']);

        $this->assertDatabaseHas('dining_tables', ['id' => $table->id]);
    }

    public function test_delete_table_succeeds_after_the_session_is_closed(): void
    {
        $restaurant = Restaurant::factory()->create();
        $table = $this->table($restaurant, 6);
        TableSession::factory()->for($restaurant)->closed()->create(['dining_table_id' => $table->id]);

        $this->actAsStaff();

        Volt::test('dining.tables')->call('delete', $table->id)->assertHasNoErrors();

        $this->assertDatabaseMissing('dining_tables', ['id' => $table->id]);
    }

    public function test_close_session_and_delete_are_scoped_to_the_panel_restaurant(): void
    {
        // Resolve which restaurant the panel scopes to (value('id') is
        // planner-order-dependent, not first-created — BUG-007).
        $x = Restaurant::factory()->create();
        $y = Restaurant::factory()->create();
        $panelId = Restaurant::query()->value('id');
        [$panel, $foreign] = $panelId === $x->id ? [$x, $y] : [$y, $x];

        $foreignTable = $this->table($foreign, 1);
        $this->placeDineInOrder($foreign, $foreignTable);

        $this->actAsStaff();

        $caught = null;
        try {
            Volt::test('dining.tables')->call('closeSession', $foreignTable->id);
        } catch (\Throwable $e) {
            $caught = $e;
        }
        $this->assertNotNull($caught, 'closeSession must not resolve a foreign table.');
        $this->assertSame(TableSessionStatus::Open, $foreignTable->openSession->status);

        $caught = null;
        try {
            Volt::test('dining.tables')->call('delete', $foreignTable->id);
        } catch (\Throwable $e) {
            $caught = $e;
        }
        $this->assertNotNull($caught, 'delete must not resolve a foreign table.');
        $this->assertDatabaseHas('dining_tables', ['id' => $foreignTable->id]);
    }
}
