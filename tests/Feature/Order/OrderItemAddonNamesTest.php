<?php

namespace Tests\Feature\Order;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The order screen lists each item's addons from the frozen snapshot
 * (order_item_addons.addon_group_name / addon_option_name).
 */
class OrderItemAddonNamesTest extends TestCase
{
    use RefreshDatabase;

    private function orderWithAddons(): Order
    {
        $order = Order::factory()->create();

        $item = OrderItem::create([
            'order_id'     => $order->id,
            'product_name' => 'Pizza Calabresa',
            'unit_price'   => 40.00,
            'quantity'     => 1,
            'subtotal'     => 52.00,
        ]);

        $item->addons()->create([
            'addon_group_name'  => 'Extras',
            'addon_option_name' => 'Bacon',
            'unit_price'        => 7.00,
            'quantity'          => 1,
            'subtotal'          => 7.00,
        ]);

        // Snapshot taken without a group name (AddOrderItem stores '' then).
        $item->addons()->create([
            'addon_group_name'  => '',
            'addon_option_name' => 'Borda de catupiry',
            'unit_price'        => 5.00,
            'quantity'          => 1,
            'subtotal'          => 5.00,
        ]);

        return $order;
    }

    public function test_order_screen_shows_the_addon_names_with_their_group(): void
    {
        $order = $this->orderWithAddons();

        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]))
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Pizza Calabresa')
            ->assertSee('+ Extras: Bacon')
            ->assertSee('+ Borda de catupiry')
            ->assertDontSee('Undefined');
    }

    public function test_order_screen_never_shows_an_empty_addon_line(): void
    {
        $order = $this->orderWithAddons();

        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]))
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertDontSeeHtml('+ </p>')
            ->assertDontSee('+ : ', false);
    }
}
