<?php

namespace Tests\Feature\Dining;

use App\Actions\Dining\AssignOrderItemToGuest;
use App\Enums\DeliveryType;
use App\Enums\DiningTableStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Exceptions\TabNotEditable;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Comanda 2/3: people at the table and item → person. Staff adds and renames
 * the people of a table session on the tab and assigns each item of the
 * session's orders to one of them. The tab shows the subtotal of each person;
 * items without a person stay in the table total. Neither the item snapshot
 * nor the order totals change.
 */
class TableTabGuestsTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        // The first restaurant is the panel's current one.
        $this->restaurant = Restaurant::factory()->create();
    }

    private function staff(UserRole $role = UserRole::Attendant): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function openTab(?Restaurant $restaurant = null, string $number = '7', bool $closed = false): TableSession
    {
        $restaurant ??= $this->restaurant;

        $table = DiningTable::create([
            'restaurant_id' => $restaurant->id,
            'number'        => $number,
            'status'        => $closed ? DiningTableStatus::Free : DiningTableStatus::Occupied,
        ]);

        $factory = TableSession::factory();

        return ($closed ? $factory->closed() : $factory)->create([
            'restaurant_id'   => $restaurant->id,
            'dining_table_id' => $table->id,
        ]);
    }

    /**
     * One order of the session with the given lines.
     *
     * @param  array<string, float>  $lines  item name => line subtotal
     */
    private function order(TableSession $session, array $lines, OrderStatus $status = OrderStatus::Delivered): Order
    {
        $total = array_sum($lines);

        $order = Order::factory()->create([
            'restaurant_id'    => $session->restaurant_id,
            'dining_table_id'  => $session->dining_table_id,
            'table_session_id' => $session->id,
            'delivery_type'    => DeliveryType::DineIn,
            'status'           => $status,
            'subtotal'         => $total,
            'delivery_fee'     => 0,
            'discount'         => 0,
            'total'            => $total,
        ]);

        foreach ($lines as $name => $subtotal) {
            OrderItem::create([
                'order_id'     => $order->id,
                'product_name' => $name,
                'unit_price'   => $subtotal,
                'quantity'     => 1,
                'subtotal'     => $subtotal,
            ]);
        }

        return $order;
    }

    private function item(Order $order, string $name): OrderItem
    {
        return $order->items()->where('product_name', $name)->firstOrFail();
    }

    private function guest(TableSession $session, string $name): TableSessionGuest
    {
        return TableSessionGuest::where('table_session_id', $session->id)->where('name', $name)->firstOrFail();
    }

    // ── Subtotal per person ─────────────────────────────────────────────

    public function test_assigning_items_to_people_shows_the_subtotal_per_person(): void
    {
        $session = $this->openTab();
        $first  = $this->order($session, ['Hambúrguer' => 30.00, 'Suco' => 20.00]);
        $second = $this->order($session, ['Pizza' => 15.50]);

        $this->actingAs($this->staff());

        $component = Volt::test('dining.tab', ['tableSession' => $session])
            ->set('newGuestName', 'Ana')->call('addGuest')->assertHasNoErrors()
            ->set('newGuestName', 'Bruno')->call('addGuest')->assertHasNoErrors();

        $ana   = $this->guest($session, 'Ana');
        $bruno = $this->guest($session, 'Bruno');

        $component
            ->call('assignItem', $this->item($first, 'Hambúrguer')->id, (string) $ana->id)
            ->call('assignItem', $this->item($first, 'Suco')->id, (string) $bruno->id)
            ->call('assignItem', $this->item($second, 'Pizza')->id, (string) $ana->id)
            ->assertHasNoErrors()
            ->assertSee('Pessoas na mesa')
            ->assertSeeHtml('data-guest-subtotal="'.$ana->id.'">R$ 45,50<')
            ->assertSeeHtml('data-guest-subtotal="'.$bruno->id.'">R$ 20,00<')
            ->assertSeeHtml('data-unassigned-subtotal>R$ 0,00<')
            ->assertSeeHtml('data-tab-total>R$ 65,50<');

        $this->assertSame($ana->id, $this->item($first, 'Hambúrguer')->table_session_guest_id);
    }

    public function test_items_without_a_person_stay_in_the_table_total(): void
    {
        $session = $this->openTab();
        $order = $this->order($session, ['Hambúrguer' => 30.00, 'Batata' => 12.00, 'Refrigerante' => 8.00]);

        $this->actingAs($this->staff());

        $component = Volt::test('dining.tab', ['tableSession' => $session])
            ->set('newGuestName', 'Ana')->call('addGuest');

        $ana = $this->guest($session, 'Ana');

        $component
            ->call('assignItem', $this->item($order, 'Hambúrguer')->id, (string) $ana->id)
            ->assertSeeHtml('data-guest-subtotal="'.$ana->id.'">R$ 30,00<')
            ->assertSeeHtml('data-unassigned-subtotal>R$ 20,00<')
            ->assertSeeHtml('data-tab-total>R$ 50,00<')
            ->assertSeeHtml('data-tab-pending>R$ 50,00<');

        // Neither the order totals nor the item snapshot changed.
        $order->refresh();
        $this->assertEquals(50.00, (float) $order->total);
        $this->assertEquals(50.00, (float) $order->subtotal);
        $burger = $this->item($order, 'Hambúrguer');
        $this->assertSame('Hambúrguer', $burger->product_name);
        $this->assertEquals(30.00, (float) $burger->subtotal);
    }

    public function test_an_item_can_go_back_to_no_person(): void
    {
        $session = $this->openTab();
        $order = $this->order($session, ['Hambúrguer' => 30.00]);
        $item = $this->item($order, 'Hambúrguer');

        $this->actingAs($this->staff());

        $component = Volt::test('dining.tab', ['tableSession' => $session])
            ->set('newGuestName', 'Ana')->call('addGuest');
        $ana = $this->guest($session, 'Ana');

        $component->call('assignItem', $item->id, (string) $ana->id)
            ->call('assignItem', $item->id, '')
            ->assertHasNoErrors()
            ->assertSeeHtml('data-guest-subtotal="'.$ana->id.'">R$ 0,00<')
            ->assertSeeHtml('data-unassigned-subtotal>R$ 30,00<');

        $this->assertNull($item->fresh()->table_session_guest_id);
    }

    public function test_items_of_a_canceled_order_are_left_out_and_cannot_be_assigned(): void
    {
        $session = $this->openTab();
        $this->order($session, ['Hambúrguer' => 30.00]);
        $canceled = $this->order($session, ['Lasanha' => 60.00], OrderStatus::Canceled);
        $lasagna = $this->item($canceled, 'Lasanha');

        $this->actingAs($this->staff());

        $component = Volt::test('dining.tab', ['tableSession' => $session])
            ->set('newGuestName', 'Ana')->call('addGuest');
        $ana = $this->guest($session, 'Ana');

        $component->call('assignItem', $lasagna->id, (string) $ana->id)
            ->assertHasErrors('assign')
            ->assertSeeHtml('data-unassigned-subtotal>R$ 30,00<')
            ->assertDontSeeHtml('data-item-guest="'.$lasagna->id.'"');

        $this->assertNull($lasagna->fresh()->table_session_guest_id);
    }

    // ── People ──────────────────────────────────────────────────────────

    public function test_a_person_without_a_name_is_numbered(): void
    {
        $session = $this->openTab();

        $this->actingAs($this->staff());

        Volt::test('dining.tab', ['tableSession' => $session])
            ->call('addGuest')
            ->call('addGuest')
            ->assertSee('Pessoa 1')
            ->assertSee('Pessoa 2');

        $this->assertSame(
            ['Pessoa 1', 'Pessoa 2'],
            TableSessionGuest::where('table_session_id', $session->id)->orderBy('id')->pluck('name')->all(),
        );
        $this->assertSame(
            [$this->restaurant->id],
            TableSessionGuest::pluck('restaurant_id')->unique()->values()->all(),
        );
    }

    public function test_a_person_can_be_renamed(): void
    {
        $session = $this->openTab();

        $this->actingAs($this->staff());

        $component = Volt::test('dining.tab', ['tableSession' => $session])->call('addGuest');
        $guest = $this->guest($session, 'Pessoa 1');

        $component->call('startRename', $guest->id)
            ->set('editingGuestName', '  Carla  ')
            ->call('saveRename')
            ->assertHasNoErrors()
            ->assertSee('Carla');

        $this->assertSame('Carla', $guest->fresh()->name);

        // An empty name is refused and the old one is kept.
        $component->call('startRename', $guest->id)
            ->set('editingGuestName', '   ')
            ->call('saveRename')
            ->assertHasErrors('editingGuestName');

        $this->assertSame('Carla', $guest->fresh()->name);
    }

    public function test_a_name_longer_than_the_limit_is_refused(): void
    {
        $session = $this->openTab();

        $this->actingAs($this->staff());

        Volt::test('dining.tab', ['tableSession' => $session])
            ->set('newGuestName', str_repeat('a', TableSessionGuest::NAME_MAX + 1))
            ->call('addGuest')
            ->assertHasErrors('newGuestName');

        $this->assertSame(0, TableSessionGuest::count());
    }

    // ── Scope ───────────────────────────────────────────────────────────

    public function test_an_item_of_another_session_cannot_be_assigned(): void
    {
        $session = $this->openTab();
        $other = $this->openTab(number: '8');
        $foreign = $this->item($this->order($other, ['Picanha' => 99.00]), 'Picanha');

        $this->actingAs($this->staff());

        $component = Volt::test('dining.tab', ['tableSession' => $session])->call('addGuest');
        $guest = $this->guest($session, 'Pessoa 1');

        $component->call('assignItem', $foreign->id, (string) $guest->id)->assertNotFound();

        $this->assertNull($foreign->fresh()->table_session_guest_id);
    }

    public function test_a_person_of_another_session_cannot_be_used(): void
    {
        $session = $this->openTab();
        $item = $this->item($this->order($session, ['Hambúrguer' => 30.00]), 'Hambúrguer');
        $other = $this->openTab(number: '8');
        $stranger = TableSessionGuest::create([
            'restaurant_id'    => $this->restaurant->id,
            'table_session_id' => $other->id,
            'name'             => 'Outra mesa',
        ]);

        $this->actingAs($this->staff());

        Volt::test('dining.tab', ['tableSession' => $session])
            ->call('assignItem', $item->id, (string) $stranger->id)
            ->assertNotFound();

        $this->assertNull($item->fresh()->table_session_guest_id);
    }

    public function test_the_action_refuses_a_person_of_another_restaurant(): void
    {
        $session = $this->openTab();
        $item = $this->item($this->order($session, ['Hambúrguer' => 30.00]), 'Hambúrguer');

        $otherRestaurant = Restaurant::factory()->create();
        $otherSession = $this->openTab($otherRestaurant, '1');
        $stranger = TableSessionGuest::create([
            'restaurant_id'    => $otherRestaurant->id,
            'table_session_id' => $otherSession->id,
            'name'             => 'Outro restaurante',
        ]);

        try {
            app(AssignOrderItemToGuest::class)->execute($item, $stranger);
            $this->fail('A person of another restaurant was accepted.');
        } catch (TabNotEditable) {
            $this->assertNull($item->fresh()->table_session_guest_id);
        }
    }

    public function test_a_closed_tab_is_read_only(): void
    {
        $session = $this->openTab(closed: true);
        $item = $this->item($this->order($session, ['Hambúrguer' => 30.00]), 'Hambúrguer');
        $guest = TableSessionGuest::create([
            'restaurant_id'    => $this->restaurant->id,
            'table_session_id' => $session->id,
            'name'             => 'Ana',
        ]);

        $this->actingAs($this->staff());

        Volt::test('dining.tab', ['tableSession' => $session])
            ->assertDontSee('Adicionar pessoa')
            ->assertDontSeeHtml('data-item-guest=')
            ->set('newGuestName', 'Bruno')
            ->call('addGuest')
            ->assertHasErrors('newGuestName')
            ->call('assignItem', $item->id, (string) $guest->id)
            ->assertHasErrors('assign');

        $this->assertSame(1, TableSessionGuest::count());
        $this->assertNull($item->fresh()->table_session_guest_id);
    }

    public function test_actions_check_the_role_again(): void
    {
        $session = $this->openTab();
        $item = $this->item($this->order($session, ['Hambúrguer' => 30.00]), 'Hambúrguer');

        $attendant = $this->staff();
        $kitchen = $this->staff(UserRole::Kitchen);

        foreach ([
            fn ($component) => $component->call('addGuest'),
            fn ($component) => $component->call('assignItem', $item->id, ''),
        ] as $action) {
            $this->actingAs($attendant);
            $component = Volt::test('dining.tab', ['tableSession' => $session]);

            // The role changed (or another user) after the screen was opened.
            $this->actingAs($kitchen);

            $action($component)->assertForbidden();
        }

        $this->assertSame(0, TableSessionGuest::count());
    }

    // ── Real route ──────────────────────────────────────────────────────

    public function test_real_route_renders_the_people_and_the_item_picker(): void
    {
        $session = $this->openTab();
        $order = $this->order($session, ['Hambúrguer' => 30.00, 'Suco' => 10.00]);
        $burger = $this->item($order, 'Hambúrguer');
        $ana = TableSessionGuest::create([
            'restaurant_id'    => $this->restaurant->id,
            'table_session_id' => $session->id,
            'name'             => 'Ana',
        ]);
        app(AssignOrderItemToGuest::class)->execute($burger, $ana);

        $this->actingAs($this->staff(UserRole::Manager))
            ->get(route('admin.dining.tab', $session))
            ->assertOk()
            ->assertDontSee('Undefined')
            ->assertSee('Pessoas na mesa')
            ->assertSee('Adicionar pessoa')
            ->assertSee('Ana')
            ->assertSee('Sem pessoa')
            ->assertSeeHtml('data-guest-subtotal="'.$ana->id.'">R$ 30,00<')
            ->assertSeeHtml('data-unassigned-subtotal>R$ 10,00<')
            ->assertSeeHtml('data-item-guest="'.$burger->id.'"');
    }
}
