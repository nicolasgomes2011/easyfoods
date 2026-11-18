<?php

namespace Tests\Feature\Order;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The kitchen sees what to prepare: each item's addons (frozen snapshot) and
 * the item note, also on the items the front of house added later.
 */
class KitchenItemDetailsTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
    }

    private function kitchen(): User
    {
        return User::factory()->create(['role' => UserRole::Kitchen]);
    }

    private function confirmedOrderWithItem(array $item = [], array $addons = []): Order
    {
        $order = Order::factory()->for($this->restaurant)->confirmed()->create();

        $line = OrderItem::create(array_merge([
            'order_id'     => $order->id,
            'product_name' => 'Pizza Calabresa',
            'unit_price'   => 40.00,
            'quantity'     => 1,
            'subtotal'     => 47.00,
        ], $item));

        foreach ($addons as $group => $option) {
            $line->addons()->create([
                'addon_group_name'  => $group,
                'addon_option_name' => $option,
                'unit_price'        => 7.00,
                'quantity'          => 1,
                'subtotal'          => 7.00,
            ]);
        }

        return $order;
    }

    public function test_kitchen_shows_the_item_addons_and_note(): void
    {
        $this->confirmedOrderWithItem(['notes' => 'sem cebola'], ['Extras' => 'Bacon']);

        $this->actingAs($this->kitchen())
            ->get(route('admin.kitchen.index'))
            ->assertOk()
            ->assertSee('Pizza Calabresa')
            ->assertSee('+ Extras: Bacon')
            ->assertSee('sem cebola')
            ->assertDontSee('Undefined');
    }

    public function test_item_added_later_keeps_its_highlight_and_shows_its_details(): void
    {
        $order = $this->confirmedOrderWithItem(
            ['product_name' => 'Refrigerante', 'notes' => 'com gelo', 'added_at' => now()],
            ['Sabor' => 'Guaraná'],
        );
        $item = $order->items()->first();

        $this->actingAs($this->kitchen())
            ->get(route('admin.kitchen.index'))
            ->assertOk()
            ->assertSeeHtml('data-added-item="'.$item->id.'"')
            ->assertSee('Novo ·')
            ->assertSee('+ Sabor: Guaraná')
            ->assertSee('com gelo');
    }

    public function test_item_without_addons_or_note_has_no_details_block(): void
    {
        $order = $this->confirmedOrderWithItem();
        $item = $order->items()->first();

        $this->actingAs($this->kitchen())
            ->get(route('admin.kitchen.index'))
            ->assertOk()
            ->assertSee('Pizza Calabresa')
            ->assertDontSeeHtml('data-item-details="'.$item->id.'"');
    }

    public function test_addons_are_loaded_with_the_items_not_once_per_item(): void
    {
        $kitchen = $this->kitchen();
        $this->confirmedOrderWithItem([], ['Extras' => 'Bacon']);

        $queriesFor = function () use ($kitchen): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($kitchen)->get(route('admin.kitchen.index'))->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        // Warm-up: the first request also resolves (and caches) the current restaurant id.
        $queriesFor();

        $withOneOrder = $queriesFor();

        $this->confirmedOrderWithItem([], ['Extras' => 'Cheddar']);
        $this->confirmedOrderWithItem([], ['Borda' => 'Catupiry']);

        $this->assertSame($withOneOrder, $queriesFor());
    }
}
