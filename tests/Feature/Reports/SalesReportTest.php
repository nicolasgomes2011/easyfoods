<?php

namespace Tests\Feature\Reports;

use App\Actions\Reports\BuildSalesReport;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Sales report (EF-47, Relatórios 1/3): revenue, number of orders and average
 * ticket in a period, by day, week or month, compared with the period of the
 * same length right before it. Canceled and draft orders are left out; only
 * the panel restaurant's orders count. Admin and manager only. The CSV export
 * has the same lines as the screen.
 */
class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private User $admin;

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

    private function orderAt(string $when, float $total, OrderStatus $status = OrderStatus::Delivered, ?Restaurant $restaurant = null): Order
    {
        $this->travelTo(CarbonImmutable::parse($when));

        return Order::factory()->create([
            'restaurant_id' => ($restaurant ?? $this->restaurant)->id,
            'status'        => $status,
            'subtotal'      => $total,
            'delivery_fee'  => 0,
            'discount'      => 0,
            'total'         => $total,
        ]);
    }

    /**
     * Known data, "today" = Friday 13/11/2026. Last 7 days = 07/11 → 13/11,
     * previous = 31/10 → 06/11.
     * In the period: 10/11 30,00 + 50,00; 12/11 25,50 (in preparation counts);
     * 13/11 44,50 (pending confirmation counts) → 4 orders, R$ 150,00, ticket R$ 37,50.
     * Left out: 12/11 canceled 99,00; 13/11 draft 80,00; another restaurant 500,00.
     * Previous period: 01/11 40,00 → 1 order, ticket R$ 40,00.
     */
    private function seedKnownWeek(): void
    {
        $this->orderAt('2026-11-01 12:00', 40.00);
        $this->orderAt('2026-11-10 12:00', 30.00);
        $this->orderAt('2026-11-10 19:00', 50.00, OrderStatus::Completed);
        $this->orderAt('2026-11-12 20:00', 25.50, OrderStatus::InPreparation);
        $this->orderAt('2026-11-12 21:00', 99.00, OrderStatus::Canceled);
        $this->orderAt('2026-11-13 10:00', 80.00, OrderStatus::Draft);
        $this->orderAt('2026-11-13 12:00', 44.50, OrderStatus::PendingConfirmation);

        $other = Restaurant::factory()->create();
        $this->orderAt('2026-11-12 13:00', 500.00, restaurant: $other);

        $this->travelTo(CarbonImmutable::parse('2026-11-13 15:00'));
    }

    private function report(string $from, string $to, string $group = 'day', ?int $restaurantId = null): array
    {
        return app(BuildSalesReport::class)->execute(
            $restaurantId ?? $this->restaurant->id,
            CarbonImmutable::parse($from),
            CarbonImmutable::parse($to),
            $group,
        );
    }

    /** @return list<array{0: string, 1: int, 2: float, 3: float}> label, orders, revenue, ticket */
    private function rows(array $report): array
    {
        return array_map(
            fn (array $row) => [$row['label'], $row['orders'], $row['revenue'], $row['average_ticket']],
            $report['rows'],
        );
    }

    // ── Aggregation ─────────────────────────────────────────────────────

    public function test_revenue_orders_and_average_ticket_of_the_period(): void
    {
        $this->seedKnownWeek();

        $report = $this->report('2026-11-07', '2026-11-13');

        $this->assertSame(4, $report['orders']);
        $this->assertSame(150.00, $report['revenue']);
        $this->assertSame(37.50, $report['average_ticket']);
    }

    public function test_revenue_by_day_lists_every_day_of_the_period(): void
    {
        $this->seedKnownWeek();

        $this->assertSame([
            ['07/11/2026', 0, 0.0, 0.0],
            ['08/11/2026', 0, 0.0, 0.0],
            ['09/11/2026', 0, 0.0, 0.0],
            ['10/11/2026', 2, 80.0, 40.0],
            ['11/11/2026', 0, 0.0, 0.0],
            ['12/11/2026', 1, 25.5, 25.5],
            ['13/11/2026', 1, 44.5, 44.5],
        ], $this->rows($this->report('2026-11-07', '2026-11-13')));
    }

    public function test_revenue_by_week_and_by_month(): void
    {
        $this->seedKnownWeek();

        // Weeks start on Monday; the first and last weeks are cut at the period edges.
        $this->assertSame([
            ['31/10 a 01/11/2026', 1, 40.0, 40.0],
            ['02/11 a 08/11/2026', 0, 0.0, 0.0],
            ['09/11 a 13/11/2026', 4, 150.0, 37.5],
        ], $this->rows($this->report('2026-10-31', '2026-11-13', 'week')));

        $this->assertSame([
            ['10/2026', 0, 0.0, 0.0],
            ['11/2026', 5, 190.0, 38.0],
        ], $this->rows($this->report('2026-10-15', '2026-11-13', 'month')));
    }

    public function test_compares_with_the_previous_period_of_the_same_length(): void
    {
        $this->seedKnownWeek();

        $report = $this->report('2026-11-07', '2026-11-13');

        $this->assertSame('2026-10-31', $report['previous_from']->toDateString());
        $this->assertSame('2026-11-06', $report['previous_to']->toDateString());
        $this->assertSame(1, $report['previous_orders']);
        $this->assertSame(40.00, $report['previous_revenue']);
        $this->assertSame(40.00, $report['previous_average_ticket']);
    }

    public function test_orders_of_another_restaurant_do_not_count(): void
    {
        $this->seedKnownWeek();

        $other = Restaurant::query()->orderByDesc('id')->firstOrFail();
        $report = $this->report('2026-11-07', '2026-11-13', restaurantId: $other->id);

        $this->assertSame(1, $report['orders']);
        $this->assertSame(500.00, $report['revenue']);

        // And our report does not see its 500,00.
        $this->assertSame(150.00, $this->report('2026-11-07', '2026-11-13')['revenue']);
    }

    public function test_a_period_without_orders_is_zero(): void
    {
        $report = $this->report('2026-11-07', '2026-11-13');

        $this->assertSame(0, $report['orders']);
        $this->assertSame(0.0, $report['revenue']);
        $this->assertSame(0.0, $report['average_ticket']);
    }

    // ── Screen ──────────────────────────────────────────────────────────

    public function test_screen_shows_the_totals_the_comparison_and_the_rows(): void
    {
        $this->seedKnownWeek();
        $this->actingAs($this->admin);

        Volt::test('reports.sales')
            ->assertSet('period', '7d')
            ->assertSeeHtml('data-sales-revenue>R$ 150,00<')
            ->assertSeeHtml('data-sales-orders>4<')
            ->assertSeeHtml('data-sales-ticket>R$ 37,50<')
            ->assertSeeHtml('data-sales-previous-revenue>R$ 40,00<')
            ->assertSee('+R$ 110,00 (+275%)')
            ->assertSeeHtml('data-sales-row="2026-11-10"')
            ->assertSee('R$ 80,00')
            ->assertDontSee('R$ 500,00')
            ->assertDontSee('R$ 99,00');
    }

    public function test_group_can_be_changed_on_the_screen(): void
    {
        $this->seedKnownWeek();
        $this->actingAs($this->admin);

        Volt::test('reports.sales')
            ->call('setGroup', 'week')
            ->assertSet('group', 'week')
            ->assertSee('09/11 a 13/11/2026')
            ->call('setGroup', 'month')
            ->assertSee('11/2026')
            // Unknown grouping: back to days.
            ->call('setGroup', 'year')
            ->assertSet('group', 'day');
    }

    public function test_period_presets_set_the_range(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-13 15:00'));
        $this->actingAs($this->admin);

        $component = Volt::test('reports.sales')
            ->assertSet('rangeFrom', '2026-11-07')
            ->assertSet('rangeTo', '2026-11-13');

        $component->call('setPeriod', 'today')
            ->assertSet('rangeFrom', '2026-11-13')
            ->assertSet('rangeTo', '2026-11-13');

        $component->call('setPeriod', '30d')
            ->assertSet('rangeFrom', '2026-10-15')
            ->assertSet('rangeTo', '2026-11-13');

        $component->call('setPeriod', 'forever')
            ->assertSet('period', '7d')
            ->assertSet('rangeFrom', '2026-11-07');
    }

    public function test_custom_period_is_validated_and_applied(): void
    {
        $this->seedKnownWeek();
        $this->actingAs($this->admin);

        Volt::test('reports.sales')
            ->set('from', '2026-11-12')
            ->set('to', '2026-11-10')
            ->call('applyCustom')
            ->assertHasErrors('to')
            ->assertSet('rangeFrom', '2026-11-07')
            ->set('from', '2025-01-01')
            ->set('to', '2026-11-10')
            ->call('applyCustom')
            ->assertHasErrors('to')
            ->set('from', '2026-11-10')
            ->set('to', '2026-11-10')
            ->call('applyCustom')
            ->assertHasNoErrors()
            ->assertSet('period', 'custom')
            ->assertSet('rangeFrom', '2026-11-10')
            ->assertSeeHtml('data-sales-revenue>R$ 80,00<');
    }

    public function test_applied_range_cannot_be_changed_from_the_browser(): void
    {
        $this->actingAs($this->admin);

        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

        Volt::test('reports.sales')->set('rangeFrom', '2000-01-01');
    }

    // ── CSV ─────────────────────────────────────────────────────────────

    public function test_csv_has_the_same_lines_as_the_screen(): void
    {
        $this->seedKnownWeek();
        $this->actingAs($this->admin);

        $component = Volt::test('reports.sales')->call('setGroup', 'week');
        $html = $component->html();

        $component->call('export')
            ->assertFileDownloaded('vendas-2026-11-07-a-2026-11-13.csv');

        $csv = base64_decode(data_get($component->effects, 'download.content'));
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        $lines = array_map(
            fn (string $line) => str_getcsv($line, ';', '"', ''),
            array_values(array_filter(explode("\n", substr($csv, 3)))),
        );

        $this->assertSame([
            ['Período', 'Pedidos', 'Faturamento', 'Ticket médio'],
            ['07/11 a 08/11/2026', '0', '0,00', '0,00'],
            ['09/11 a 13/11/2026', '4', '150,00', '37,50'],
            ['Total', '4', '150,00', '37,50'],
        ], $lines);

        // Every line of the file is a line of the screen table.
        foreach (array_slice($lines, 1) as [$label, $orders, $revenue, $ticket]) {
            $this->assertStringContainsString($label, $html);
            $this->assertStringContainsString('R$ '.$revenue, $html);
            $this->assertStringContainsString('R$ '.$ticket, $html);
        }
        $this->assertSame(2, substr_count($html, 'data-sales-row='));
    }

    public function test_export_checks_the_role_again(): void
    {
        $this->actingAs($this->admin);
        $component = Volt::test('reports.sales');

        $this->actingAs(User::factory()->create(['role' => UserRole::Attendant]));

        $component->call('export')->assertForbidden();
    }

    // ── Access ──────────────────────────────────────────────────────────

    public function test_kitchen_attendant_and_delivery_cannot_open_the_report(): void
    {
        foreach ([UserRole::Kitchen, UserRole::Attendant, UserRole::Delivery] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('admin.reports.sales'))->assertForbidden();

            // Livewire actions do not go through the route middleware again: the component checks the role too.
            Volt::test('reports.sales')->assertForbidden();
        }
    }

    public function test_real_route_renders_for_admin_and_manager(): void
    {
        $this->seedKnownWeek();

        foreach ([UserRole::Admin, UserRole::Manager] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('admin.reports.sales'))
                ->assertOk()
                ->assertDontSee('Undefined')
                ->assertSee('Vendas por período')
                ->assertSee('Exportar CSV')
                ->assertSee('R$ 150,00');
        }
    }

    public function test_reports_screen_links_the_sales_report_for_admin_and_manager_only(): void
    {
        foreach ([UserRole::Admin, UserRole::Manager] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('admin.reports.index'))
                ->assertOk()
                ->assertSee(route('admin.reports.sales'), false)
                ->assertSeeHtml('data-report="sales"');
        }

        $this->actingAs(User::factory()->create(['role' => UserRole::Kitchen]))
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertDontSee(route('admin.reports.sales'), false);

        // The sales card is no longer a "coming soon" one (nor, since EF-48, top products and peak hours).
        $html = $this->actingAs($this->admin)->get(route('admin.reports.index'))->getContent();
        $this->assertSame(3, substr_count($html, 'Em desenvolvimento'));
    }
}
