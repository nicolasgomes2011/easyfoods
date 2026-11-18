<?php

namespace Tests\Feature\Reports;

use App\Actions\Reports\BuildProductsReport;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Products and peak hours report (EF-48, Relatórios 2/3): top products by
 * quantity and by revenue, and orders by hour of the day and by day of the
 * week, in a period. Same orders as the sales report: canceled and draft left
 * out, only the panel restaurant's. Admin and manager only. The CSV export has
 * the same lines as the screen.
 */
class ProductsReportTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $admin;

    /** @var array<string, Product> */
    private array $products = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Lowest id: the restaurant the panel scopes to (CurrentRestaurant::id()).
        $this->restaurant = Restaurant::factory()->create();
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    private function product(string $key, string $name, ?Restaurant $restaurant = null): Product
    {
        $restaurant ??= $this->restaurant;

        $category = Category::withoutGlobalScopes()->firstOrCreate(
            ['restaurant_id' => $restaurant->id, 'slug' => 'lanches'],
            ['name' => 'Lanches'],
        );

        return $this->products[$key] = Product::withoutGlobalScopes()->create([
            'restaurant_id' => $restaurant->id,
            'category_id'   => $category->id,
            'name'          => $name,
            'price'         => 10,
        ]);
    }

    /**
     * @param  list<array{0: ?Product, 1: string, 2: int, 3: float}>  $items  product, name snapshot, quantity, line subtotal
     */
    private function orderAt(string $when, array $items, OrderStatus $status = OrderStatus::Delivered, ?Restaurant $restaurant = null): Order
    {
        $this->travelTo(CarbonImmutable::parse($when));

        $total = array_sum(array_column($items, 3));

        $order = Order::factory()->create([
            'restaurant_id' => ($restaurant ?? $this->restaurant)->id,
            'status'        => $status,
            'subtotal'      => $total,
            'delivery_fee'  => 0,
            'discount'      => 0,
            'total'         => $total,
        ]);

        foreach ($items as [$product, $name, $quantity, $subtotal]) {
            OrderItem::create([
                'order_id'     => $order->id,
                'product_id'   => $product?->id,
                'product_name' => $name,
                'unit_price'   => round($subtotal / $quantity, 2),
                'quantity'     => $quantity,
                'subtotal'     => $subtotal,
            ]);
        }

        return $order;
    }

    /**
     * Known data, "today" = Friday 13/11/2026. Last 7 days = 07/11 → 13/11.
     * In the period (4 orders):
     * - Tue 10/11 12:30: X-Burger ×2 (50,00) + Batata ×1 (12,00)
     * - Tue 10/11 19:10: X-Burger ×1 (25,00)
     * - Thu 12/11 19:40, in preparation: "X-Burger Especial" (same product, renamed) ×1 (30,00) + Refrigerante ×4 (24,00)
     * - Fri 13/11 19:05, pending confirmation: Refrigerante ×2 (12,00) + Pudim (product deleted) ×1 (15,00)
     * Left out: canceled Thu 21:00 (Batata ×10), draft Fri 10:00 (X-Burger ×5),
     * another restaurant Thu 13:00 (Suco ×50), previous period 01/11 (Batata ×3).
     */
    private function seedKnownWeek(): void
    {
        $burger = $this->product('burger', 'X-Burger Especial');
        $fries = $this->product('fries', 'Batata');
        $soda = $this->product('soda', 'Refrigerante');

        $this->orderAt('2026-11-01 12:00', [[$fries, 'Batata', 3, 36.00]]);
        $this->orderAt('2026-11-10 12:30', [[$burger, 'X-Burger', 2, 50.00], [$fries, 'Batata', 1, 12.00]]);
        $this->orderAt('2026-11-10 19:10', [[$burger, 'X-Burger', 1, 25.00]], OrderStatus::Completed);
        $this->orderAt('2026-11-12 19:40', [[$burger, 'X-Burger Especial', 1, 30.00], [$soda, 'Refrigerante', 4, 24.00]], OrderStatus::InPreparation);
        $this->orderAt('2026-11-12 21:00', [[$fries, 'Batata', 10, 120.00]], OrderStatus::Canceled);
        $this->orderAt('2026-11-13 10:00', [[$burger, 'X-Burger', 5, 125.00]], OrderStatus::Draft);
        $this->orderAt('2026-11-13 19:05', [[$soda, 'Refrigerante', 2, 12.00], [null, 'Pudim', 1, 15.00]], OrderStatus::PendingConfirmation);

        $other = Restaurant::factory()->create();
        $juice = $this->product('juice', 'Suco Outro Restaurante', $other);
        $this->orderAt('2026-11-12 13:00', [[$juice, 'Suco Outro Restaurante', 50, 500.00]], restaurant: $other);

        $this->travelTo(CarbonImmutable::parse('2026-11-13 15:00'));
    }

    private function report(string $from = '2026-11-07', string $to = '2026-11-13', ?int $restaurantId = null): array
    {
        return app(BuildProductsReport::class)->execute(
            $restaurantId ?? $this->restaurant->id,
            CarbonImmutable::parse($from),
            CarbonImmutable::parse($to),
        );
    }

    /** @return list<array{0: string, 1: int, 2: float}> name, quantity, revenue */
    private function ranking(array $rows): array
    {
        return array_map(fn (array $row) => [$row['name'], $row['quantity'], $row['revenue']], $rows);
    }

    // ── Aggregation ─────────────────────────────────────────────────────

    public function test_top_products_by_quantity_and_by_revenue(): void
    {
        $this->seedKnownWeek();

        $report = $this->report();

        $this->assertSame(4, $report['orders']);

        // Ties on quantity: higher revenue first.
        $this->assertSame([
            ['Refrigerante', 6, 36.0],
            ['X-Burger Especial', 4, 105.0],
            ['Pudim', 1, 15.0],
            ['Batata', 1, 12.0],
        ], $this->ranking($report['by_quantity']));

        $this->assertSame([
            ['X-Burger Especial', 4, 105.0],
            ['Refrigerante', 6, 36.0],
            ['Pudim', 1, 15.0],
            ['Batata', 1, 12.0],
        ], $this->ranking($report['by_revenue']));
    }

    public function test_a_renamed_product_is_one_line_with_its_latest_name(): void
    {
        $this->seedKnownWeek();

        $names = array_column($this->report()['by_quantity'], 'name');

        $this->assertContains('X-Burger Especial', $names);
        $this->assertNotContains('X-Burger', $names);
    }

    public function test_orders_by_hour_and_by_day_of_the_week_with_the_peaks(): void
    {
        $this->seedKnownWeek();

        $report = $this->report();

        $this->assertCount(24, $report['by_hour']);
        $this->assertSame(1, $report['by_hour'][12]);
        $this->assertSame(3, $report['by_hour'][19]);
        // Canceled (21h), draft (10h) and the other restaurant (13h) are left out.
        $this->assertSame(0, $report['by_hour'][21]);
        $this->assertSame(0, $report['by_hour'][10]);
        $this->assertSame(0, $report['by_hour'][13]);
        $this->assertSame(4, array_sum($report['by_hour']));
        $this->assertSame(19, $report['peak_hour']);

        // Monday = 1 … Sunday = 7.
        $this->assertSame([1 => 0, 2 => 2, 3 => 0, 4 => 1, 5 => 1, 6 => 0, 7 => 0], $report['by_weekday']);
        $this->assertSame(2, $report['peak_weekday']);
    }

    public function test_only_the_given_restaurant_is_counted(): void
    {
        $this->seedKnownWeek();

        $other = Restaurant::query()->orderByDesc('id')->first();
        $report = $this->report(restaurantId: $other->id);

        $this->assertSame(1, $report['orders']);
        $this->assertSame([['Suco Outro Restaurante', 50, 500.0]], $this->ranking($report['by_quantity']));
        $this->assertSame(13, $report['peak_hour']);
    }

    public function test_empty_period_has_no_products_and_no_peaks(): void
    {
        $report = $this->report('2026-11-07', '2026-11-13');

        $this->assertSame(0, $report['orders']);
        $this->assertSame([], $report['by_quantity']);
        $this->assertSame([], $report['by_revenue']);
        $this->assertSame(0, array_sum($report['by_hour']));
        $this->assertNull($report['peak_hour']);
        $this->assertNull($report['peak_weekday']);
    }

    // ── Screen ──────────────────────────────────────────────────────────

    public function test_screen_shows_the_rankings_and_the_peaks(): void
    {
        $this->seedKnownWeek();
        $this->actingAs($this->admin);

        Volt::test('reports.products')
            ->assertSet('period', '7d')
            ->assertSeeHtml('data-products-orders>4<')
            ->assertSeeHtml('data-peak-hour>19h<')
            ->assertSeeHtml('data-peak-weekday>Terça<')
            ->assertSeeHtml('data-top-quantity="1">Refrigerante<')
            ->assertSeeHtml('data-top-revenue="1">X-Burger Especial<')
            ->assertSee('R$ 105,00')
            ->assertSeeHtml('data-hour="19" data-orders="3"')
            ->assertSeeHtml('data-weekday="2" data-orders="2"')
            ->assertDontSee('Suco Outro Restaurante');
    }

    public function test_screen_period_can_be_changed(): void
    {
        $this->seedKnownWeek();
        $this->actingAs($this->admin);

        Volt::test('reports.products')
            ->call('setPeriod', 'today')
            ->assertSet('rangeFrom', '2026-11-13')
            ->assertSeeHtml('data-products-orders>1<')
            ->assertSeeHtml('data-top-quantity="1">Refrigerante<')
            ->assertDontSee('X-Burger Especial');
    }

    public function test_empty_period_shows_a_message(): void
    {
        $this->actingAs($this->admin);

        Volt::test('reports.products')
            ->assertSee('Nenhum pedido neste período.')
            ->assertDontSeeHtml('data-peak-hour');
    }

    // ── CSV ─────────────────────────────────────────────────────────────

    public function test_csv_has_the_same_lines_as_the_screen(): void
    {
        $this->seedKnownWeek();
        $this->actingAs($this->admin);

        $component = Volt::test('reports.products');
        $html = $component->html();

        $component->call('export')
            ->assertFileDownloaded('produtos-e-horarios-2026-11-07-a-2026-11-13.csv');

        $csv = base64_decode(data_get($component->effects, 'download.content'));
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        $lines = array_map(
            fn (string $line) => str_getcsv($line, ';', '"', ''),
            array_values(array_filter(explode("\n", substr($csv, 3)))),
        );

        $this->assertSame(['Mais vendidos (quantidade)', 'Quantidade', 'Receita'], $lines[0]);
        $this->assertSame(['Refrigerante', '6', '36,00'], $lines[1]);
        $this->assertSame(['Batata', '1', '12,00'], $lines[4]);
        $this->assertSame(['Maior receita', 'Quantidade', 'Receita'], $lines[5]);
        $this->assertSame(['X-Burger Especial', '4', '105,00'], $lines[6]);
        $this->assertSame(['Hora', 'Pedidos'], $lines[10]);
        $this->assertSame(['00h', '0'], $lines[11]);
        $this->assertSame(['19h', '3'], $lines[30]);
        $this->assertSame(['Dia da semana', 'Pedidos'], $lines[35]);
        $this->assertSame(['Segunda', '0'], $lines[36]);
        $this->assertSame(['Terça', '2'], $lines[37]);
        $this->assertCount(43, $lines);

        // Every product line of the file is on the screen.
        foreach ([...array_slice($lines, 1, 4), ...array_slice($lines, 6, 4)] as [$name, $quantity, $revenue]) {
            $this->assertStringContainsString($name, $html);
            $this->assertStringContainsString('R$ '.$revenue, $html);
        }
    }

    public function test_export_checks_the_role_again(): void
    {
        $this->actingAs($this->admin);
        $component = Volt::test('reports.products');

        $this->actingAs(User::factory()->create(['role' => UserRole::Attendant]));

        $component->call('export')->assertForbidden();
    }

    // ── Access ──────────────────────────────────────────────────────────

    public function test_kitchen_attendant_and_delivery_cannot_open_the_report(): void
    {
        foreach ([UserRole::Kitchen, UserRole::Attendant, UserRole::Delivery] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('admin.reports.products'))->assertForbidden();

            // Livewire actions do not go through the route middleware again: the component checks the role too.
            Volt::test('reports.products')->assertForbidden();
        }
    }

    public function test_real_route_renders_for_admin_and_manager(): void
    {
        $this->seedKnownWeek();

        foreach ([UserRole::Admin, UserRole::Manager] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('admin.reports.products'))
                ->assertOk()
                ->assertDontSee('Undefined')
                ->assertSee('Itens mais pedidos e horários de pico')
                ->assertSee('Exportar CSV')
                ->assertSee('X-Burger Especial')
                ->assertSee('R$ 105,00');
        }
    }

    public function test_reports_screen_links_the_products_report_for_admin_and_manager_only(): void
    {
        foreach ([UserRole::Admin, UserRole::Manager] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('admin.reports.index'))
                ->assertOk()
                ->assertSee(route('admin.reports.products'), false)
                ->assertSeeHtml('data-report="products"');
        }

        // Reports area is admin/manager only (role access matrix, EF-10).
        $this->actingAs(User::factory()->create(['role' => UserRole::Kitchen]))
            ->get(route('admin.reports.index'))
            ->assertForbidden();
    }
}
