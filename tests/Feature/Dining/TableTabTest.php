<?php

namespace Tests\Feature\Dining;

use App\Enums\DeliveryType;
use App\Enums\DiningTableStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * "Comanda da mesa" (Salão › Mesas): every order of a table session with its
 * items, the consolidated total (canceled orders left out), what was already
 * paid (payments with status paid) and what is still pending. Staff of the
 * dining room only (admin, manager, attendant); a session of another
 * restaurant is a 404.
 */
class TableTabTest extends TestCase
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

    private function openTab(?Restaurant $restaurant = null, string $number = '7'): TableSession
    {
        $restaurant ??= $this->restaurant;

        $table = DiningTable::create([
            'restaurant_id' => $restaurant->id,
            'number'        => $number,
            'status'        => DiningTableStatus::Occupied,
        ]);

        return TableSession::factory()->create([
            'restaurant_id'   => $restaurant->id,
            'dining_table_id' => $table->id,
        ]);
    }

    private function order(TableSession $session, float $total, OrderStatus $status = OrderStatus::Delivered, string $item = 'Hambúrguer'): Order
    {
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

        OrderItem::create([
            'order_id'     => $order->id,
            'product_name' => $item,
            'unit_price'   => $total,
            'quantity'     => 1,
            'subtotal'     => $total,
        ]);

        return $order;
    }

    private function payment(Order $order, float $amount, PaymentStatus $status = PaymentStatus::Paid): Payment
    {
        return Payment::create([
            'order_id' => $order->id,
            'method'   => PaymentMethod::Cash,
            'status'   => $status,
            'amount'   => $amount,
            'paid_at'  => $status === PaymentStatus::Paid ? now() : null,
        ]);
    }

    public function test_tab_lists_every_order_of_the_session_and_the_consolidated_total(): void
    {
        $session = $this->openTab();
        $first  = $this->order($session, 30.00, item: 'Hambúrguer');
        $second = $this->order($session, 45.50, OrderStatus::InPreparation, 'Pizza');
        $third  = $this->order($session, 24.50, OrderStatus::Confirmed, 'Suco');

        $this->actingAs($this->staff());

        $component = Volt::test('dining.tab', ['tableSession' => $session])
            ->assertSee('Mesa 7')
            ->assertSee('Hambúrguer')
            ->assertSee('Pizza')
            ->assertSee('Suco')
            ->assertSeeHtml('data-tab-total>R$ 100,00<');

        foreach ([$first, $second, $third] as $order) {
            $component->assertSeeHtml('data-tab-order="'.$order->id.'"');
        }
    }

    public function test_canceled_orders_are_listed_but_left_out_of_the_total(): void
    {
        $session = $this->openTab();
        $this->order($session, 40.00);
        $canceled = $this->order($session, 60.00, OrderStatus::Canceled, 'Lasanha');

        $this->actingAs($this->staff());

        Volt::test('dining.tab', ['tableSession' => $session])
            ->assertSeeHtml('data-tab-order="'.$canceled->id.'"')
            ->assertSee('Cancelado')
            ->assertSeeHtml('data-tab-total>R$ 40,00<')
            ->assertSeeHtml('data-tab-pending>R$ 40,00<');
    }

    public function test_paid_and_pending_come_from_the_payments(): void
    {
        $session = $this->openTab();
        $first  = $this->order($session, 30.00);
        $second = $this->order($session, 45.50);
        $this->order($session, 24.50);

        $this->payment($first, 30.00);
        // A pending payment is not money received yet.
        $this->payment($second, 45.50, PaymentStatus::Pending);

        $this->actingAs($this->staff());

        Volt::test('dining.tab', ['tableSession' => $session])
            ->assertSeeHtml('data-tab-total>R$ 100,00<')
            ->assertSeeHtml('data-tab-paid>R$ 30,00<')
            ->assertSeeHtml('data-tab-pending>R$ 70,00<');
    }

    public function test_payment_of_a_canceled_order_does_not_count_as_paid(): void
    {
        $session = $this->openTab();
        $this->order($session, 50.00);
        $canceled = $this->order($session, 20.00, OrderStatus::Canceled);
        $this->payment($canceled, 20.00);

        $this->actingAs($this->staff());

        Volt::test('dining.tab', ['tableSession' => $session])
            ->assertSeeHtml('data-tab-total>R$ 50,00<')
            ->assertSeeHtml('data-tab-paid>R$ 0,00<')
            ->assertSeeHtml('data-tab-pending>R$ 50,00<');
    }

    public function test_orders_of_another_session_are_not_on_the_tab(): void
    {
        $session = $this->openTab();
        $other = $this->openTab(number: '8');
        $this->order($session, 30.00);
        $foreign = $this->order($other, 99.00, item: 'Picanha');

        $this->actingAs($this->staff());

        Volt::test('dining.tab', ['tableSession' => $session])
            ->assertDontSeeHtml('data-tab-order="'.$foreign->id.'"')
            ->assertDontSee('Picanha')
            ->assertSeeHtml('data-tab-total>R$ 30,00<');
    }

    public function test_session_of_another_restaurant_is_a_404(): void
    {
        $otherRestaurant = Restaurant::factory()->create();
        $session = $this->openTab($otherRestaurant);
        $this->order($session, 30.00);

        $this->actingAs($this->staff(UserRole::Manager))
            ->get(route('admin.dining.tab', $session))
            ->assertNotFound();
    }

    public function test_kitchen_and_delivery_cannot_open_the_tab(): void
    {
        $session = $this->openTab();

        foreach ([UserRole::Kitchen, UserRole::Delivery] as $role) {
            $this->actingAs($this->staff($role))
                ->get(route('admin.dining.tab', $session))
                ->assertForbidden();
        }
    }

    public function test_real_route_renders_for_dining_room_staff(): void
    {
        $session = $this->openTab();
        $this->order($session, 30.00);

        foreach ([UserRole::Admin, UserRole::Manager, UserRole::Attendant] as $role) {
            $this->actingAs($this->staff($role))
                ->get(route('admin.dining.tab', $session))
                ->assertOk()
                ->assertDontSee('Undefined')
                ->assertSee('Comanda da mesa')
                ->assertSee('R$ 30,00');
        }
    }

    public function test_tables_screen_links_the_tab_of_a_table_with_an_open_session(): void
    {
        $session = $this->openTab();
        $free = DiningTable::create([
            'restaurant_id' => $this->restaurant->id,
            'number'        => '9',
            'status'        => DiningTableStatus::Free,
        ]);

        $this->actingAs($this->staff());

        $html = Volt::test('dining.tables')->html();

        $this->assertStringContainsString(route('admin.dining.tab', $session), $html);
        $this->assertSame(1, substr_count($html, 'data-tab-link'));
        $this->assertStringNotContainsString('data-tab-link="'.$free->id.'"', $html);
    }

    public function test_tables_screen_hides_the_tab_link_from_kitchen(): void
    {
        $this->openTab();

        $this->actingAs($this->staff(UserRole::Kitchen));

        $this->assertStringNotContainsString('data-tab-link', Volt::test('dining.tables')->html());
    }
}
