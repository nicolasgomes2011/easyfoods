<?php

namespace Tests\Feature\Store;

use App\Actions\Cart\AddItemToCart;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\TransitionOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * The customer's note belongs to one cart line: adding the same product with
 * another note opens a new line (the note reaches the order and the kitchen),
 * while the same note — or no note on both — keeps adding to the same line.
 */
class CartLineNotesTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;
    private Product $burger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create(['accepts_pickup' => true]);

        $category = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name'          => 'Lanches',
            'slug'          => 'lanches',
            'is_active'     => true,
            'sort_order'    => 1,
        ]);

        $this->burger = Product::create([
            'restaurant_id'       => $this->restaurant->id,
            'category_id'         => $category->id,
            'name'                => 'Burger',
            'price'               => 30.00,
            'availability_status' => 'available',
            'sort_order'          => 1,
            'is_featured'         => false,
        ]);
    }

    private function addFromMenu(string $notes = '', int $qty = 1): void
    {
        Volt::test('store.menu')
            ->call('openProduct', $this->burger->id)
            ->set('qty', $qty)
            ->set('notes', $notes)
            ->call('addToCart', $this->burger->id)
            ->assertSet('modalError', null);
    }

    private function addWithAction(?string $notes, int $quantity = 1): CartItem
    {
        // Same session as the menu component, so both paths share one cart.
        return app(AddItemToCart::class)
            ->handle($this->restaurant, session()->getId(), $this->burger, null, [], $quantity, $notes);
    }

    /** @return array<int, array{quantity:int, notes:?string}> cart lines in insertion order */
    private function cartLines(): array
    {
        return CartItem::orderBy('id')->get()
            ->map(fn (CartItem $item) => ['quantity' => (int) $item->quantity, 'notes' => $item->notes])
            ->all();
    }

    private function placePickupOrder(): Order
    {
        return app(PlaceOrder::class)->handle(Cart::firstOrFail(), [
            'delivery_type' => 'pickup',
            'customer_name' => 'Cliente Teste',
        ]);
    }

    // ── Different notes → separate lines ─────────────────────────────────────

    public function test_a_note_added_after_a_plain_line_opens_a_new_line(): void
    {
        $this->addFromMenu();
        $this->addFromMenu('sem amendoim');

        $this->assertSame([
            ['quantity' => 1, 'notes' => null],
            ['quantity' => 1, 'notes' => 'sem amendoim'],
        ], $this->cartLines());
    }

    public function test_a_plain_add_after_a_noted_line_does_not_inherit_the_note(): void
    {
        $this->addFromMenu('sem cebola');
        $this->addFromMenu();

        $this->assertSame([
            ['quantity' => 1, 'notes' => 'sem cebola'],
            ['quantity' => 1, 'notes' => null],
        ], $this->cartLines());
    }

    public function test_two_different_notes_keep_both(): void
    {
        $this->addWithAction('sem cebola');
        $this->addWithAction('bem passado', 2);

        $this->assertSame([
            ['quantity' => 1, 'notes' => 'sem cebola'],
            ['quantity' => 2, 'notes' => 'bem passado'],
        ], $this->cartLines());
    }

    // ── Same note (or none) → same line ──────────────────────────────────────

    public function test_the_same_note_keeps_adding_to_the_same_line(): void
    {
        $this->addFromMenu('sem cebola');
        $this->addFromMenu('  sem cebola ', 2); // spaces around do not make it another note

        $this->assertSame([['quantity' => 3, 'notes' => 'sem cebola']], $this->cartLines());
    }

    public function test_no_note_on_both_keeps_adding_to_the_same_line(): void
    {
        $this->addWithAction(null);
        $this->addWithAction('   '); // blank counts as no note
        $this->addFromMenu();

        $this->assertSame([['quantity' => 3, 'notes' => null]], $this->cartLines());
    }

    // ── The placed order keeps each note on its line ─────────────────────────

    public function test_the_placed_order_has_one_item_per_note(): void
    {
        $this->addFromMenu();
        $this->addFromMenu('sem amendoim');

        $order = $this->placePickupOrder();

        $items = $order->items()->orderBy('id')->get();
        $this->assertSame(
            [['Burger', 1, null], ['Burger', 1, 'sem amendoim']],
            $items->map(fn ($i) => [$i->product_name, (int) $i->quantity, $i->notes])->all()
        );
        $this->assertSame('60.00', number_format((float) $order->total, 2, '.', ''));
    }

    public function test_order_detail_and_kitchen_show_the_note_on_its_line(): void
    {
        $this->addFromMenu();
        $this->addFromMenu('sem amendoim');
        $order = $this->placePickupOrder();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('sem amendoim')
            ->assertDontSee('Undefined');

        app(TransitionOrderStatus::class)->execute($order->fresh(), OrderStatus::Confirmed, $admin);

        $this->get(route('admin.kitchen.index'))
            ->assertOk()
            ->assertSee('Obs: sem amendoim')
            ->assertDontSee('Undefined');

        // Two kitchen lines of ×1 each, never one merged ×2 line.
        Volt::test('kitchen.index')
            ->assertSee('Obs: sem amendoim')
            ->assertSee('Burger ×1')
            ->assertDontSee('Burger ×2');
    }

    public function test_real_menu_and_cart_routes_render(): void
    {
        $this->get(route('store.menu'))->assertOk()->assertSee('Burger')->assertDontSee('Undefined');
        $this->get(route('store.cart'))->assertOk()->assertDontSee('Undefined');
    }
}
