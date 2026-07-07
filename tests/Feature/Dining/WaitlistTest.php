<?php

namespace Tests\Feature\Dining;

use App\Actions\Orders\PlaceOrder;
use App\Enums\DiningTableStatus;
use App\Enums\UserRole;
use App\Enums\WaitlistStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Dining light (Phase 2 close-out): the walk-in waitlist queue and the
 * PlaceOrder side-effect that occupies the table on dine-in orders.
 */
class WaitlistTest extends TestCase
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

    public function test_queue_page_renders_with_empty_state(): void
    {
        Restaurant::factory()->create();
        $this->actAsStaff();

        $this->get(route('admin.dining.queue'))
            ->assertOk()
            ->assertSee('Fila de espera')
            ->assertSee('Ninguém aguardando.')
            ->assertDontSee('próxima fase'); // the old stub is gone
    }

    public function test_add_creates_a_waiting_entry_and_requires_name_and_size(): void
    {
        $restaurant = Restaurant::factory()->create();
        $this->actAsStaff();

        Volt::test('dining.queue')
            ->call('add')
            ->assertHasErrors(['partyName', 'partySize']);

        Volt::test('dining.queue')
            ->set('partyName', 'Família Silva')
            ->set('partySize', '4')
            ->set('phone', '(11) 99999-0000')
            ->call('add')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('waitlist_entries', [
            'restaurant_id' => $restaurant->id,
            'party_name'    => 'Família Silva',
            'party_size'    => 4,
            'status'        => WaitlistStatus::Waiting->value,
        ]);
    }

    public function test_waiting_list_is_fifo(): void
    {
        $restaurant = Restaurant::factory()->create();
        $first  = WaitlistEntry::factory()->for($restaurant)->create(['party_name' => 'Primeiro', 'created_at' => now()->subMinutes(20)]);
        $second = WaitlistEntry::factory()->for($restaurant)->create(['party_name' => 'Segundo', 'created_at' => now()->subMinutes(5)]);

        $this->actAsStaff();

        $names = Volt::test('dining.queue')->instance()->waiting->pluck('party_name')->all();
        $this->assertSame(['Primeiro', 'Segundo'], $names);
    }

    public function test_seating_with_a_table_marks_entry_seated_and_occupies_the_table(): void
    {
        $restaurant = Restaurant::factory()->create();
        $table = $this->table($restaurant, 7);
        $entry = WaitlistEntry::factory()->for($restaurant)->create();

        $this->actAsStaff();

        Volt::test('dining.queue')
            ->call('openSeat', $entry->id)
            ->set('tableId', (string) $table->id)
            ->call('seat', $entry->id)
            ->assertHasNoErrors();

        $entry->refresh();
        $this->assertSame(WaitlistStatus::Seated, $entry->status);
        $this->assertNotNull($entry->seated_at);
        $this->assertSame($table->id, $entry->dining_table_id);
        $this->assertSame(DiningTableStatus::Occupied, $table->fresh()->status);
    }

    public function test_seating_without_a_table_just_marks_seated(): void
    {
        $restaurant = Restaurant::factory()->create();
        $entry = WaitlistEntry::factory()->for($restaurant)->create();

        $this->actAsStaff();

        Volt::test('dining.queue')
            ->call('seat', $entry->id)
            ->assertHasNoErrors();

        $entry->refresh();
        $this->assertSame(WaitlistStatus::Seated, $entry->status);
        $this->assertNull($entry->dining_table_id);
    }

    public function test_remove_marks_entry_removed_with_timestamp(): void
    {
        $restaurant = Restaurant::factory()->create();
        $entry = WaitlistEntry::factory()->for($restaurant)->create();

        $this->actAsStaff();

        Volt::test('dining.queue')->call('remove', $entry->id);

        $entry->refresh();
        $this->assertSame(WaitlistStatus::Removed, $entry->status);
        $this->assertNotNull($entry->removed_at);
    }

    public function test_waitlist_actions_are_scoped_to_the_panel_restaurant(): void
    {
        // Resolve which restaurant the panel scopes to (value('id') is
        // planner-order-dependent, not first-created — BUG-007).
        $x = Restaurant::factory()->create();
        $y = Restaurant::factory()->create();
        $panelId = Restaurant::query()->value('id');
        [$panel, $foreign] = $panelId === $x->id ? [$x, $y] : [$y, $x];

        $foreignEntry = WaitlistEntry::factory()->for($foreign)->create();
        $panelEntry   = WaitlistEntry::factory()->for($panel)->create();
        $foreignTable = $this->table($foreign, 9);

        $this->actAsStaff();

        // Foreign entry is not actionable.
        $caught = null;
        try {
            Volt::test('dining.queue')->call('seat', $foreignEntry->id);
        } catch (\Throwable $e) {
            $caught = $e;
        }
        $this->assertNotNull($caught, 'seat must not resolve a foreign waitlist entry.');
        $this->assertSame(WaitlistStatus::Waiting, $foreignEntry->fresh()->status);

        // A foreign table cannot be used to seat a local entry.
        $caught = null;
        try {
            Volt::test('dining.queue')
                ->set('tableId', (string) $foreignTable->id)
                ->call('seat', $panelEntry->id);
        } catch (\Throwable $e) {
            $caught = $e;
        }
        $this->assertNotNull($caught, 'seat must not resolve a foreign table.');
        $this->assertSame(WaitlistStatus::Waiting, $panelEntry->fresh()->status);
        $this->assertSame(DiningTableStatus::Free, $foreignTable->fresh()->status);
    }

    public function test_place_order_occupies_the_table_on_dine_in(): void
    {
        $restaurant = Restaurant::factory()->create();
        $table = $this->table($restaurant, 3);
        $cart = $this->cartWithItem($restaurant);

        app(PlaceOrder::class)->handle($cart, [
            'delivery_type'   => 'dine_in',
            'customer_name'   => 'Mesa Três',
            'dining_table_id' => $table->id,
            'table_number'    => (string) $table->number,
        ]);

        $this->assertSame(DiningTableStatus::Occupied, $table->fresh()->status);
    }

    public function test_place_order_does_not_touch_tables_on_pickup(): void
    {
        $restaurant = Restaurant::factory()->create();
        $table = $this->table($restaurant, 4);
        $cart = $this->cartWithItem($restaurant);

        app(PlaceOrder::class)->handle($cart, [
            'delivery_type' => 'pickup',
            'customer_name' => 'Retirada',
        ]);

        $this->assertSame(DiningTableStatus::Free, $table->fresh()->status);
    }

    public function test_place_order_ignores_a_foreign_table_id(): void
    {
        $restaurant = Restaurant::factory()->create();
        $other      = Restaurant::factory()->create();
        $foreignTable = $this->table($other, 1);
        $cart = $this->cartWithItem($restaurant);

        app(PlaceOrder::class)->handle($cart, [
            'delivery_type'   => 'dine_in',
            'customer_name'   => 'Intruso',
            'dining_table_id' => $foreignTable->id,
            'table_number'    => '1',
        ]);

        // Scoped update matched zero rows — the foreign table stays free.
        $this->assertSame(DiningTableStatus::Free, $foreignTable->fresh()->status);
    }

    private function cartWithItem(Restaurant $restaurant): Cart
    {
        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Lanches '.$restaurant->id,
            'slug'          => 'lanches-'.$restaurant->id,
            'is_active'     => true,
            'sort_order'    => 1,
        ]);

        $product = Product::create([
            'restaurant_id'       => $restaurant->id,
            'category_id'         => $category->id,
            'name'                => 'X-Burger '.$restaurant->id,
            'price'               => 20.00,
            'availability_status' => 'available',
            'sort_order'          => 1,
            'is_featured'         => false,
        ]);

        $cart = Cart::create([
            'session_id'    => 'session-'.$restaurant->id,
            'restaurant_id' => $restaurant->id,
        ]);

        CartItem::create([
            'cart_id'    => $cart->id,
            'product_id' => $product->id,
            'quantity'   => 1,
        ]);

        return $cart;
    }
}
