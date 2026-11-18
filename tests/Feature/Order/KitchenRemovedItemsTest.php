<?php

namespace Tests\Feature\Order;

use App\Actions\Orders\RemoveOrderItem;
use App\Actions\Orders\TransitionOrderStatus;
use App\Enums\DeliveryType;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Kitchen screen (EF-73): an item the front of house removes from a dine-in
 * order after the kitchen got it (confirmed) stays on the card, struck through,
 * with quantity, time and reason, so the kitchen stops preparing it.
 */
class KitchenRemovedItemsTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $attendant;

    protected function setUp(): void
    {
        parent::setUp();

        // Lowest id: the restaurant the panel scopes to (CurrentRestaurant::id()).
        $this->restaurant = Restaurant::factory()->create();
        $this->attendant = User::factory()->create(['role' => UserRole::Attendant]);
    }

    private function kitchen(): User
    {
        return User::factory()->create(['role' => UserRole::Kitchen]);
    }

    /** Dine-in order with a Hambúrguer ×2 and a Refrigerante ×1. */
    private function dineInOrder(OrderStatus $status, array $attributes = []): Order
    {
        $order = Order::factory()->for($this->restaurant)->create(array_merge([
            'status'        => $status,
            'delivery_type' => DeliveryType::DineIn,
            'delivery_fee'  => 0,
            'discount'      => 0,
            'subtotal'      => 66.00,
            'total'         => 66.00,
        ], $attributes));

        foreach ([['Hambúrguer', 2, 30.00], ['Refrigerante', 1, 6.00]] as [$name, $quantity, $price]) {
            OrderItem::create([
                'order_id'     => $order->id,
                'product_name' => $name,
                'unit_price'   => $price,
                'quantity'     => $quantity,
                'subtotal'     => $price * $quantity,
            ]);
        }

        return $order->fresh();
    }

    private function inPreparationOrder(): Order
    {
        return $this->dineInOrder(OrderStatus::InPreparation, [
            'confirmed_at'           => now()->subMinutes(10),
            'preparation_started_at' => now()->subMinutes(5),
        ]);
    }

    private function remove(Order $order, string $productName, string $reason): void
    {
        $item = $order->items()->where('product_name', $productName)->firstOrFail();

        app(RemoveOrderItem::class)->execute($order, $item->id, $reason, $this->attendant);
    }

    public function test_item_removed_while_in_preparation_shows_as_removed_on_the_kitchen_card(): void
    {
        // Fix the clock before the order exists, so the removal at 12:34 always
        // comes after the confirmation (12:20), whatever time the suite runs.
        $this->travelTo(now()->setTime(12, 30));
        $order = $this->inPreparationOrder();
        $this->travelTo(now()->setTime(12, 34));
        $this->remove($order, 'Hambúrguer', 'cliente desistiu');

        $removal = $order->itemChanges()->sole();

        $this->actingAs($this->kitchen())
            ->get(route('admin.kitchen.index'))
            ->assertOk()
            ->assertDontSee('Undefined')
            ->assertSeeHtml('data-removed-item="'.$removal->id.'"')
            ->assertSee('Hambúrguer ×2')
            ->assertSee('Removido · 12:34')
            ->assertSee('Motivo: cliente desistiu')
            ->assertSee('Refrigerante ×1');
    }

    public function test_kitchen_component_lists_the_removal_struck_through(): void
    {
        $order = $this->inPreparationOrder();
        $this->remove($order, 'Hambúrguer', 'pedido errado');

        $this->actingAs($this->kitchen());

        Volt::test('kitchen.index')
            ->assertSeeHtml('line-through')
            ->assertSee('Hambúrguer ×2')
            ->assertSee('Motivo: pedido errado');
    }

    public function test_item_removed_before_confirmation_is_not_shown_as_removed(): void
    {
        $order = $this->dineInOrder(OrderStatus::PendingConfirmation);
        $this->remove($order, 'Hambúrguer', 'cliente trocou de ideia');

        // The kitchen only gets the order once it is confirmed, after the removal.
        $this->travel(5)->minutes();
        app(TransitionOrderStatus::class)->execute($order->fresh(), OrderStatus::Confirmed, $this->attendant);

        $this->actingAs($this->kitchen())
            ->get(route('admin.kitchen.index'))
            ->assertOk()
            ->assertSee('Refrigerante ×1')
            ->assertDontSeeHtml('data-removed-item=')
            ->assertDontSee('Removido ·')
            ->assertDontSee('Hambúrguer');
    }

    public function test_remaining_and_added_items_still_show_as_before(): void
    {
        $order = $this->inPreparationOrder();
        $added = OrderItem::create([
            'order_id'     => $order->id,
            'product_name' => 'Batata frita',
            'unit_price'   => 15.00,
            'quantity'     => 1,
            'subtotal'     => 15.00,
            'added_at'     => now(),
        ]);
        $this->remove($order, 'Hambúrguer', 'cliente desistiu');

        $this->actingAs($this->kitchen())
            ->get(route('admin.kitchen.index'))
            ->assertOk()
            ->assertSee('Refrigerante ×1')
            ->assertSeeHtml('data-added-item="'.$added->id.'"')
            ->assertSee('Novo ·')
            ->assertSee('Batata frita ×1')
            ->assertSeeHtml('data-removed-item=');
    }

    public function test_order_without_removals_has_no_removed_line(): void
    {
        $this->inPreparationOrder();

        $this->actingAs($this->kitchen())
            ->get(route('admin.kitchen.index'))
            ->assertOk()
            ->assertSee('Hambúrguer ×2')
            ->assertDontSeeHtml('data-removed-item=');
    }

    public function test_removals_are_loaded_with_the_queue_not_once_per_order(): void
    {
        $kitchen = $this->kitchen();
        $this->remove($this->inPreparationOrder(), 'Hambúrguer', 'cliente desistiu');

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

        $this->remove($this->inPreparationOrder(), 'Refrigerante', 'sem estoque');
        $this->remove($this->inPreparationOrder(), 'Hambúrguer', 'mesa foi embora');

        $this->assertSame($withOneOrder, $queriesFor());
    }
}
