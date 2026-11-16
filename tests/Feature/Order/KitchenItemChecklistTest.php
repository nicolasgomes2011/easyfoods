<?php

namespace Tests\Feature\Order;

use App\Actions\Orders\ToggleOrderItemPrepared;
use App\Actions\Orders\TransitionOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Kitchen checklist per item (EF-43): while an order is in preparation the
 * kitchen ticks each item off; "Marcar pronto" is enabled only when every item
 * is ticked, and the server refuses it otherwise. The status still changes
 * only through TransitionOrderStatus. Scoped to the panel restaurant.
 */
class KitchenItemChecklistTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        // Lowest id: the restaurant the panel scopes to (CurrentRestaurant::id()).
        $this->restaurant = Restaurant::factory()->create();
        $this->actingAs(User::factory()->create(['role' => UserRole::Kitchen]));
    }

    private function item(Order $order, string $name, array $extra = []): OrderItem
    {
        return OrderItem::create(array_merge([
            'order_id'     => $order->id,
            'product_name' => $name,
            'unit_price'   => 20.00,
            'quantity'     => 1,
            'subtotal'     => 20.00,
        ], $extra));
    }

    /** An order in preparation with two items: [order, burger, fries]. */
    private function orderInPreparation(?Restaurant $restaurant = null): array
    {
        $order = Order::factory()->for($restaurant ?? $this->restaurant)->inPreparation()->create(['ready_at' => null]);

        return [$order, $this->item($order, 'Hambúrguer'), $this->item($order, 'Batata frita')];
    }

    public function test_ticking_every_item_enables_ready_and_unticking_disables_it(): void
    {
        [$order, $burger, $fries] = $this->orderInPreparation();

        $component = Volt::test('kitchen.index')
            ->assertSeeHtml('data-kitchen-ready-blocked="'.$order->id.'"')
            ->assertSee('0 de 2 itens');

        $component->call('toggleItemPrepared', $burger->id)
            ->assertSee('1 de 2 itens')
            ->assertSeeHtml('data-kitchen-ready-blocked="'.$order->id.'"');
        $this->assertNotNull($burger->fresh()->prepared_at);

        $component->call('toggleItemPrepared', $fries->id)
            ->assertSee('2 de 2 itens')
            ->assertDontSeeHtml('data-kitchen-ready-blocked="'.$order->id.'"');

        $component->call('toggleItemPrepared', $fries->id)
            ->assertSee('1 de 2 itens')
            ->assertSeeHtml('data-kitchen-ready-blocked="'.$order->id.'"');
        $this->assertNull($fries->fresh()->prepared_at);
    }

    public function test_ready_is_refused_on_the_server_while_an_item_is_not_ticked(): void
    {
        [$order, $burger] = $this->orderInPreparation();

        Volt::test('kitchen.index')
            ->call('toggleItemPrepared', $burger->id)
            ->call('markReady', $order->id)
            ->assertSee('Marque todos os itens do pedido #'.$order->number.' antes de marcar pronto.');

        $this->assertSame(OrderStatus::InPreparation, $order->fresh()->status);
        $this->assertDatabaseMissing('order_status_histories', [
            'order_id'  => $order->id,
            'to_status' => OrderStatus::ReadyForPickup->value,
        ]);
    }

    public function test_ready_goes_through_once_every_item_is_ticked(): void
    {
        [$order, $burger, $fries] = $this->orderInPreparation();

        Volt::test('kitchen.index')
            ->call('toggleItemPrepared', $burger->id)
            ->call('toggleItemPrepared', $fries->id)
            ->call('markReady', $order->id)
            ->assertDontSee('antes de marcar pronto');

        $order->refresh();
        $this->assertSame(OrderStatus::ReadyForPickup, $order->status);
        $this->assertNotNull($order->ready_at);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id'    => $order->id,
            'from_status' => OrderStatus::InPreparation->value,
            'to_status'   => OrderStatus::ReadyForPickup->value,
        ]);
    }

    public function test_an_item_added_later_blocks_ready_again(): void
    {
        [$order, $burger, $fries] = $this->orderInPreparation();
        $burger->forceFill(['prepared_at' => now()])->save();
        $fries->forceFill(['prepared_at' => now()])->save();

        $soda = $this->item($order, 'Refrigerante', ['added_at' => now()]);

        Volt::test('kitchen.index')
            ->assertSee('2 de 3 itens')
            ->assertSeeHtml('data-kitchen-ready-blocked="'.$order->id.'"')
            ->assertSeeHtml('data-item-check="'.$soda->id.'"');
    }

    public function test_item_of_another_restaurant_cannot_be_ticked(): void
    {
        [, $theirBurger] = $this->orderInPreparation(Restaurant::factory()->create());

        Volt::test('kitchen.index')
            ->call('toggleItemPrepared', $theirBurger->id)
            ->assertStatus(404);

        $this->assertNull($theirBurger->fresh()->prepared_at);
    }

    public function test_items_are_ticked_only_while_the_order_is_in_preparation(): void
    {
        $order = Order::factory()->for($this->restaurant)->confirmed()->create();
        $burger = $this->item($order, 'Hambúrguer');

        Volt::test('kitchen.index')
            ->assertDontSeeHtml('data-item-check="'.$burger->id.'"')
            ->call('toggleItemPrepared', $burger->id);

        $this->assertNull($burger->fresh()->prepared_at);
    }

    public function test_delivery_user_cannot_tick_items(): void
    {
        [, $burger] = $this->orderInPreparation();
        $this->actingAs(User::factory()->create(['role' => UserRole::Delivery]));

        Volt::test('kitchen.index')
            ->call('toggleItemPrepared', $burger->id)
            ->assertForbidden();

        $this->assertNull($burger->fresh()->prepared_at);
    }

    public function test_real_route_shows_the_checklist(): void
    {
        [$order, $burger, $fries] = $this->orderInPreparation();
        $burger->forceFill(['prepared_at' => now()])->save();

        $this->get(route('admin.kitchen.index'))
            ->assertOk()
            ->assertDontSee('Undefined')
            ->assertSee('1 de 2 itens')
            ->assertSee('Marcar pronto')
            ->assertSee('data-item-check="'.$burger->id.'"', false)
            ->assertSee('data-item-check="'.$fries->id.'"', false)
            ->assertSee('data-kitchen-ready-blocked="'.$order->id.'"', false);
    }

    // ── EF-80: the rule is enforced by the transition, under the order lock ──

    public function test_the_transition_refuses_ready_while_an_item_is_not_prepared(): void
    {
        [$order, $burger] = $this->orderInPreparation();
        $burger->forceFill(['prepared_at' => now()])->save();

        try {
            app(TransitionOrderStatus::class)->execute($order, OrderStatus::ReadyForPickup, auth()->user());
            $this->fail('TransitionOrderStatus should refuse "Pronto" with an item not prepared.');
        } catch (ValidationException $e) {
            $this->assertSame(
                'Marque todos os itens do pedido #'.$order->number.' antes de marcar pronto.',
                $e->errors()['status'][0]
            );
        }

        $order->refresh();
        $this->assertSame(OrderStatus::InPreparation, $order->status);
        $this->assertNull($order->ready_at);
        $this->assertDatabaseMissing('order_status_histories', [
            'order_id'  => $order->id,
            'to_status' => OrderStatus::ReadyForPickup->value,
        ]);
        $this->assertDatabaseCount('prep_samples', 0);
    }

    public function test_an_item_added_between_the_screen_check_and_the_transition_keeps_the_order_in_preparation(): void
    {
        [$order, $burger, $fries] = $this->orderInPreparation();
        $burger->forceFill(['prepared_at' => now()])->save();
        $fries->forceFill(['prepared_at' => now()])->save();

        // The attendant's item lands right after the kitchen screen checked the
        // items and right before the locked transition runs.
        $this->app->bind(TransitionOrderStatus::class, fn () => new class extends TransitionOrderStatus {
            public function execute(Order $order, OrderStatus $to, ?User $actor, ?string $notes = null): Order
            {
                OrderItem::create([
                    'order_id'     => $order->id,
                    'product_name' => 'Refrigerante',
                    'unit_price'   => 6.00,
                    'quantity'     => 1,
                    'subtotal'     => 6.00,
                    'added_at'     => now(),
                ]);

                return parent::execute($order, $to, $actor, $notes);
            }
        });

        Volt::test('kitchen.index')
            ->call('markReady', $order->id)
            ->assertHasNoErrors()
            ->assertSee('Marque todos os itens do pedido #'.$order->number.' antes de marcar pronto.');

        $this->assertSame(OrderStatus::InPreparation, $order->fresh()->status);
        $this->assertDatabaseMissing('order_status_histories', [
            'order_id'  => $order->id,
            'to_status' => OrderStatus::ReadyForPickup->value,
        ]);
    }

    public function test_an_order_with_every_item_prepared_still_goes_ready_through_the_transition(): void
    {
        [$order, $burger, $fries] = $this->orderInPreparation();
        $burger->forceFill(['prepared_at' => now()])->save();
        $fries->forceFill(['prepared_at' => now()])->save();

        app(TransitionOrderStatus::class)->execute($order, OrderStatus::ReadyForPickup, auth()->user());

        $this->assertSame(OrderStatus::ReadyForPickup, $order->fresh()->status);
    }

    // ── EF-85: ticking/unticking runs under the order lock ──

    public function test_an_order_marked_ready_between_the_screen_read_and_the_untick_keeps_its_items_ticked(): void
    {
        [$order, $burger, $fries] = $this->orderInPreparation();
        $burger->forceFill(['prepared_at' => now()])->save();
        $fries->forceFill(['prepared_at' => now()])->save();

        // Another kitchen screen marks the order ready right after this screen
        // read the item (order still in preparation) and right before the write.
        $this->app->bind(ToggleOrderItemPrepared::class, fn () => new class extends ToggleOrderItemPrepared {
            public function execute(OrderItem $item): bool
            {
                app(TransitionOrderStatus::class)->execute($item->order, OrderStatus::ReadyForPickup, auth()->user());

                return parent::execute($item);
            }
        });

        Volt::test('kitchen.index')
            ->call('toggleItemPrepared', $fries->id)
            ->assertHasNoErrors();

        $this->assertSame(OrderStatus::ReadyForPickup, $order->fresh()->status);
        $this->assertNotNull($fries->fresh()->prepared_at);
        $this->assertSame(0, $order->items()->whereNull('prepared_at')->count());
    }

    public function test_the_toggle_does_not_write_once_the_order_left_preparation(): void
    {
        [$order, $burger, $fries] = $this->orderInPreparation();
        $burger->forceFill(['prepared_at' => now()])->save();
        $fries->forceFill(['prepared_at' => now()])->save();
        $readWhileInPreparation = $fries->fresh()->load('order');

        app(TransitionOrderStatus::class)->execute($order, OrderStatus::ReadyForPickup, auth()->user());

        $this->assertFalse(app(ToggleOrderItemPrepared::class)->execute($readWhileInPreparation));
        $this->assertNotNull($fries->fresh()->prepared_at);
    }

    public function test_the_toggle_flips_the_stored_value_not_a_stale_copy(): void
    {
        [, $burger] = $this->orderInPreparation();
        $readBeforeTheTick = $burger->fresh()->load('order');
        $toggle = app(ToggleOrderItemPrepared::class);

        $this->assertTrue($toggle->execute($burger));
        $this->assertNotNull($burger->fresh()->prepared_at);

        // The old copy still says "not prepared"; the stored row says prepared, so it unticks.
        $this->assertTrue($toggle->execute($readBeforeTheTick));
        $this->assertNull($burger->fresh()->prepared_at);
    }

    public function test_the_toggle_does_not_write_an_item_that_left_the_order(): void
    {
        [, $burger] = $this->orderInPreparation();
        $readBeforeRemoval = $burger->fresh()->load('order');
        $burger->delete();

        $this->assertFalse(app(ToggleOrderItemPrepared::class)->execute($readBeforeRemoval));
        $this->assertDatabaseMissing('order_items', ['id' => $burger->id]);
    }
}
