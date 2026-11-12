<?php

namespace Tests\Feature\Order;

use App\Actions\Orders\AddOrderItem;
use App\Actions\Orders\RemoveOrderItem;
use App\Enums\DeliveryType;
use App\Enums\OrderItemChangeType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Exceptions\OrderNotEditable;
use App\Models\AddonGroup;
use App\Models\AddonOption;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemChange;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Staff can add items to and remove items from an open dine-in order (before it
 * is ready). Lines are frozen like at checkout, totals are recomputed on the
 * server, every change is logged and the order status never moves.
 */
class EditOpenOrderItemsTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private Product $pizza;
    private ProductVariant $large;
    private AddonOption $bacon;
    private AddonOption $cheese;
    private AddonGroup $extras;
    private Product $soda;
    private User $attendant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();

        $this->pizza = $this->product($this->restaurant, 'Pizza', 40.00);
        $this->large = ProductVariant::create([
            'product_id'          => $this->pizza->id,
            'name'                => 'Grande',
            'price'               => 60.00,
            'availability_status' => 'available',
            'sort_order'          => 1,
        ]);
        $this->extras = AddonGroup::create([
            'product_id'  => $this->pizza->id,
            'name'        => 'Extras',
            'required'    => false,
            'min_choices' => 0,
            'max_choices' => 3,
            'sort_order'  => 1,
            'is_active'   => true,
        ]);
        $this->bacon = $this->option($this->extras, 'Bacon', 4.00);
        $this->cheese = $this->option($this->extras, 'Queijo', 3.00);

        $this->soda = $this->product($this->restaurant, 'Refrigerante', 6.00);

        $this->attendant = User::factory()->create(['role' => UserRole::Attendant]);
    }

    private function product(Restaurant $restaurant, string $name, float $price): Product
    {
        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Cat '.$name,
            'slug'          => 'cat-'.strtolower($name).'-'.$restaurant->id,
            'is_active'     => true,
            'sort_order'    => 1,
        ]);

        return Product::create([
            'restaurant_id'       => $restaurant->id,
            'category_id'         => $category->id,
            'name'                => $name,
            'price'               => $price,
            'availability_status' => 'available',
            'sort_order'          => 1,
        ]);
    }

    private function option(AddonGroup $group, string $name, float $price): AddonOption
    {
        return AddonOption::create([
            'addon_group_id' => $group->id,
            'name'           => $name,
            'price'          => $price,
            'is_active'      => true,
            'sort_order'     => 1,
        ]);
    }

    /** Dine-in order placed with one Pizza (R$ 40,00), plus the extra lines given. */
    private function order(
        OrderStatus $status = OrderStatus::Confirmed,
        DeliveryType $type = DeliveryType::DineIn,
        array $extraLines = [],
        float $discount = 0,
    ): Order {
        $order = Order::factory()->create([
            'restaurant_id' => $this->restaurant->id,
            'status'        => $status,
            'delivery_type' => $type,
            'delivery_fee'  => 0,
            'discount'      => $discount,
            'subtotal'      => 0,
            'total'         => 0,
        ]);

        $lines = array_merge([[$this->pizza, 1, 40.00]], $extraLines);
        $subtotal = 0;

        foreach ($lines as [$product, $quantity, $unitPrice]) {
            OrderItem::create([
                'order_id'     => $order->id,
                'product_id'   => $product->id,
                'product_name' => $product->name,
                'unit_price'   => $unitPrice,
                'quantity'     => $quantity,
                'subtotal'     => $unitPrice * $quantity,
            ]);
            $subtotal += $unitPrice * $quantity;
        }

        $order->update(['subtotal' => $subtotal, 'total' => $subtotal - $discount]);

        OrderStatusHistory::create([
            'order_id'    => $order->id,
            'from_status' => null,
            'to_status'   => $status->value,
            'changed_by'  => null,
            'changed_at'  => now(),
        ]);

        return $order->fresh();
    }

    private function add(Order $order, Product $product, ?int $variantId = null, array $addons = [], int $quantity = 1, ?string $notes = null): OrderItem
    {
        return app(AddOrderItem::class)->execute($order, $product, $variantId, $addons, $quantity, $notes, $this->attendant);
    }

    public function test_adding_an_item_freezes_the_line_and_recalculates_the_totals(): void
    {
        $order = $this->order();

        $item = $this->add($order, $this->pizza, $this->large->id, [$this->extras->id => [$this->bacon->id, $this->cheese->id]], 2, ' sem cebola ');

        // (60 + 4 + 3) × 2 = 134; order: 40 + 134 = 174
        $item->refresh();
        $this->assertSame('Pizza', $item->product_name);
        $this->assertSame('Grande', $item->variant_name);
        $this->assertSame('60.00', $item->unit_price);
        $this->assertSame(2, $item->quantity);
        $this->assertSame('134.00', $item->subtotal);
        $this->assertSame('sem cebola', $item->notes);
        $this->assertTrue($item->wasAddedLater());
        $this->assertEqualsCanonicalizing(['Bacon', 'Queijo'], $item->addons->pluck('addon_option_name')->all());
        $this->assertSame(['Extras', 'Extras'], $item->addons->pluck('addon_group_name')->all());

        $order->refresh();
        $this->assertSame('174.00', $order->subtotal);
        $this->assertSame('174.00', $order->total);

        $change = OrderItemChange::where('order_id', $order->id)->sole();
        $this->assertSame(OrderItemChangeType::Added, $change->type);
        $this->assertSame($item->id, $change->order_item_id);
        $this->assertSame($this->restaurant->id, $change->restaurant_id);
        $this->assertSame($this->attendant->id, $change->changed_by);
        $this->assertSame('134.00', $change->subtotal);

        // The status does not move and no status history is written.
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame(1, OrderStatusHistory::where('order_id', $order->id)->count());
    }

    public function test_the_snapshot_keeps_the_price_charged_when_the_menu_price_changes_later(): void
    {
        $order = $this->order();
        $item = $this->add($order, $this->soda, quantity: 2);

        $this->soda->update(['price' => 9.99]);

        $this->assertSame('6.00', $item->fresh()->unit_price);
        $this->assertSame('52.00', $order->fresh()->total);
    }

    public function test_removing_an_item_logs_who_and_why_and_recalculates_the_totals(): void
    {
        $order = $this->order(extraLines: [[$this->soda, 2, 6.00]], discount: 5);
        $soda = $order->items()->where('product_name', 'Refrigerante')->sole();

        app(RemoveOrderItem::class)->execute($order, $soda->id, '  cliente desistiu ', $this->attendant);

        $this->assertDatabaseMissing('order_items', ['id' => $soda->id]);

        $order->refresh();
        $this->assertSame('40.00', $order->subtotal);
        $this->assertSame('35.00', $order->total); // 40 - 5 of discount

        $change = OrderItemChange::where('order_id', $order->id)->sole();
        $this->assertSame(OrderItemChangeType::Removed, $change->type);
        $this->assertSame('Refrigerante', $change->product_name);
        $this->assertSame(2, $change->quantity);
        $this->assertSame('12.00', $change->subtotal);
        $this->assertSame('cliente desistiu', $change->reason);
        $this->assertSame($this->attendant->id, $change->changed_by);
        $this->assertSame(OrderStatus::Confirmed, $order->status);
    }

    public function test_removing_an_item_with_addons_removes_its_addons_too(): void
    {
        $order = $this->order();
        $item = $this->add($order, $this->pizza, $this->large->id, [$this->extras->id => [$this->bacon->id]]);

        app(RemoveOrderItem::class)->execute($order, $item->id, 'pedido errado', $this->attendant);

        $this->assertDatabaseMissing('order_item_addons', ['order_item_id' => $item->id]);
        $this->assertSame('40.00', $order->fresh()->total);
    }

    public function test_removal_needs_a_reason_and_keeps_at_least_one_item(): void
    {
        $order = $this->order(extraLines: [[$this->soda, 1, 6.00]]);
        $soda = $order->items()->where('product_name', 'Refrigerante')->sole();
        $pizza = $order->items()->where('product_name', 'Pizza')->sole();

        try {
            app(RemoveOrderItem::class)->execute($order, $soda->id, '  ', $this->attendant);
            $this->fail('A removal without reason was accepted.');
        } catch (OrderNotEditable) {
        }

        app(RemoveOrderItem::class)->execute($order, $soda->id, 'sem gelo no estoque', $this->attendant);

        $this->expectException(OrderNotEditable::class);
        app(RemoveOrderItem::class)->execute($order, $pizza->id, 'cliente foi embora', $this->attendant);
    }

    public function test_an_item_of_another_order_cannot_be_removed(): void
    {
        $order = $this->order(extraLines: [[$this->soda, 1, 6.00]]);
        $other = $this->order(extraLines: [[$this->soda, 1, 6.00]]);
        $foreignItem = $other->items()->first();

        $this->expectException(OrderNotEditable::class);

        try {
            app(RemoveOrderItem::class)->execute($order, $foreignItem->id, 'tentativa', $this->attendant);
        } finally {
            $this->assertDatabaseHas('order_items', ['id' => $foreignItem->id]);
        }
    }

    public function test_delivery_and_pickup_orders_cannot_be_edited(): void
    {
        foreach ([DeliveryType::Delivery, DeliveryType::Pickup] as $type) {
            $order = $this->order(type: $type, extraLines: [[$this->soda, 1, 6.00]]);

            $this->assertFalse($order->itemsAreEditable());
            $this->assertFalse($this->attendant->can('editItems', $order));
            $this->assertRefused(fn () => $this->add($order, $this->soda));
            $this->assertRefused(fn () => app(RemoveOrderItem::class)
                ->execute($order, $order->items()->first()->id, 'motivo qualquer', $this->attendant));
        }

        $this->assertSame(0, OrderItemChange::count());
    }

    public function test_only_orders_not_ready_yet_can_be_edited(): void
    {
        foreach ([OrderStatus::PendingConfirmation, OrderStatus::Confirmed, OrderStatus::InPreparation] as $status) {
            $order = $this->order($status);
            $this->add($order, $this->soda);
            $this->assertSame('46.00', $order->fresh()->total, $status->value);
        }

        foreach ([OrderStatus::ReadyForPickup, OrderStatus::Delivered, OrderStatus::Completed, OrderStatus::Canceled] as $status) {
            $order = $this->order($status);

            $this->assertFalse($this->attendant->can('editItems', $order), $status->value);
            $this->assertRefused(fn () => $this->add($order, $this->soda));
            $this->assertSame('40.00', $order->fresh()->total, $status->value);
        }
    }

    public function test_products_must_be_orderable_items_of_the_same_restaurant(): void
    {
        $order = $this->order();

        $foreign = $this->product(Restaurant::factory()->create(), 'Estrangeiro', 1.00);
        $this->assertRefused(fn () => $this->add($order, $foreign));

        $this->soda->update(['availability_status' => 'out_of_stock']);
        $this->assertRefused(fn () => $this->add($order, $this->soda));

        $this->soda->update(['availability_status' => 'available', 'archived_at' => now()]);
        $this->assertRefused(fn () => $this->add($order, $this->soda));

        // Pizza has variants: one must be chosen, and it must be the pizza's own.
        $this->assertRefused(fn () => $this->add($order, $this->pizza));

        // An addon option of another product is refused.
        $mineralWater = $this->product($this->restaurant, 'Agua', 4.00);
        $ice = $this->option(AddonGroup::create([
            'product_id' => $mineralWater->id, 'name' => 'Gelo', 'required' => false,
            'min_choices' => 0, 'max_choices' => 1, 'sort_order' => 1, 'is_active' => true,
        ]), 'Com gelo', 0.50);
        $this->assertRefused(fn () => $this->add($order, $this->pizza, $this->large->id, [$this->extras->id => [$ice->id]]));

        $this->assertRefused(fn () => $this->add($order, $this->soda, quantity: 0));

        $this->assertSame(1, $order->items()->count());
        $this->assertSame('40.00', $order->fresh()->total);
    }

    public function test_an_open_payment_follows_the_new_total(): void
    {
        $order = $this->order();
        $payment = Payment::create([
            'order_id'        => $order->id,
            'method'          => PaymentMethod::Cash,
            'status'          => PaymentStatus::Pending,
            'amount'          => 40.00,
            'amount_tendered' => 100.00,
            'change_due'      => 60.00,
        ]);

        $this->add($order, $this->soda);

        $payment->refresh();
        $this->assertSame('46.00', $payment->amount);
        $this->assertSame('54.00', $payment->change_due);
    }

    public function test_a_paid_order_cannot_change_its_items(): void
    {
        $order = $this->order(extraLines: [[$this->soda, 1, 6.00]]);
        Payment::create([
            'order_id' => $order->id,
            'method'   => PaymentMethod::Pix,
            'status'   => PaymentStatus::Paid,
            'amount'   => 46.00,
            'paid_at'  => now(),
        ]);

        $this->assertRefused(fn () => $this->add($order, $this->soda));
        $this->assertRefused(fn () => app(RemoveOrderItem::class)
            ->execute($order, $order->items()->first()->id, 'motivo qualquer', $this->attendant));

        $this->assertSame('46.00', $order->fresh()->total);
    }

    public function test_attendant_adds_and_removes_items_on_the_order_screen(): void
    {
        $order = $this->order();
        $this->actingAs($this->attendant);

        $component = Volt::test('orders.show', ['order' => $order])
            ->assertSee('Adicionar item')
            ->call('openAddItem')
            ->assertSee('Refrigerante')
            ->set('newProductId', (string) $this->soda->id)
            ->set('newQuantity', '2')
            ->set('newNotes', 'sem gelo')
            ->call('addItem')
            ->assertHasNoErrors()
            ->assertSee('Item adicionado: 2× Refrigerante.')
            ->assertSee('Alterações nos itens');

        $this->assertSame('52.00', $order->fresh()->total);
        $soda = $order->items()->where('product_name', 'Refrigerante')->sole();

        $component
            ->call('startRemoving', $soda->id)
            ->call('removeItem')
            ->assertHasErrors('removeReason')
            ->set('removeReason', 'cliente desistiu')
            ->call('removeItem')
            ->assertHasNoErrors()
            ->assertSee('Item removido do pedido.')
            ->assertSee('Motivo: cliente desistiu');

        $this->assertSame('40.00', $order->fresh()->total);
    }

    public function test_order_screen_shows_the_refusal_reason(): void
    {
        $order = $this->order();
        $this->actingAs($this->attendant);

        Volt::test('orders.show', ['order' => $order])
            ->call('openAddItem')
            ->set('newProductId', (string) $this->pizza->id)
            ->call('addItem')
            ->assertHasErrors('newItem')
            ->assertSee('Escolha uma opção.');

        $this->assertSame(1, $order->items()->count());
    }

    public function test_kitchen_and_delivery_roles_cannot_edit_items(): void
    {
        $order = $this->order(extraLines: [[$this->soda, 1, 6.00]]);

        foreach ([UserRole::Kitchen, UserRole::Delivery] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));

            Volt::test('orders.show', ['order' => $order])
                ->call('openAddItem')
                ->assertForbidden();

            Volt::test('orders.show', ['order' => $order])
                ->set('removingItemId', $order->items()->first()->id)
                ->set('removeReason', 'tentativa')
                ->call('removeItem')
                ->assertForbidden();
        }

        $this->assertSame(2, $order->items()->count());
    }

    public function test_order_screen_offers_editing_only_for_open_dine_in_orders(): void
    {
        $this->actingAs($this->attendant);

        $this->get(route('admin.orders.show', $this->order()))
            ->assertOk()
            ->assertDontSee('Undefined')
            ->assertSee('Adicionar item');

        $this->get(route('admin.orders.show', $this->order(type: DeliveryType::Delivery)))
            ->assertOk()
            ->assertDontSee('Adicionar item');

        $this->get(route('admin.orders.show', $this->order(OrderStatus::ReadyForPickup)))
            ->assertOk()
            ->assertDontSee('Adicionar item');
    }

    public function test_kitchen_highlights_items_added_after_the_order_was_placed(): void
    {
        $order = $this->order(OrderStatus::InPreparation);
        $original = $order->items()->first();
        $added = $this->add($order, $this->soda);

        $this->actingAs(User::factory()->create(['role' => UserRole::Kitchen]))
            ->get(route('admin.kitchen.index'))
            ->assertOk()
            ->assertDontSee('Undefined')
            ->assertSeeHtml('data-added-item="'.$added->id.'"')
            ->assertDontSeeHtml('data-added-item="'.$original->id.'"')
            ->assertSee('Novo · '.$added->added_at->format('H:i'));
    }

    private function assertRefused(callable $attempt): void
    {
        try {
            $attempt();
        } catch (OrderNotEditable) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('The change was accepted but should have been refused.');
    }
}
