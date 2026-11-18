<?php

namespace Tests\Feature\Waste;

use App\Actions\Waste\BuildWasteReport;
use App\Actions\Waste\CorrectWasteEntry;
use App\Actions\Waste\RecordWaste;
use App\Enums\IngredientCategory;
use App\Enums\IngredientUnit;
use App\Enums\UserRole;
use App\Enums\WasteReason;
use App\Models\Ingredient;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\WasteEntry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Waste report (EF-41): money lost in a period (quantity × snapshot unit cost),
 * ranked by ingredient, category and reason, compared with the period before.
 * A correction counts in the period of the entry it corrects. Admin and manager
 * only; scoped to the panel restaurant.
 */
class WasteReportTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private Ingredient $tomato;

    private Ingredient $beef;

    private Ingredient $milk;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Lowest id: the restaurant the panel scopes to (CurrentRestaurant::id()).
        $this->restaurant = Restaurant::factory()->create();
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->tomato = $this->ingredient($this->restaurant, 'Tomate', IngredientUnit::Kilogram, IngredientCategory::Produce, 8.50);
        $this->beef   = $this->ingredient($this->restaurant, 'Carne moída', IngredientUnit::Kilogram, IngredientCategory::Meat, 40.00);
        $this->milk   = $this->ingredient($this->restaurant, 'Leite', IngredientUnit::Liter, IngredientCategory::Dairy, 5.00);
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        parent::tearDown();
    }

    private function ingredient(Restaurant $restaurant, string $name, IngredientUnit $unit, IngredientCategory $category, float $cost): Ingredient
    {
        return Ingredient::factory()->for($restaurant)->create([
            'name'          => $name,
            'unit'          => $unit,
            'category'      => $category,
            'cost_per_unit' => $cost,
        ]);
    }

    private function recordAt(string $when, Ingredient $ingredient, string $quantity, WasteReason $reason): WasteEntry
    {
        $this->travelTo(CarbonImmutable::parse($when));

        return app(RecordWaste::class)->execute($ingredient, $quantity, $reason, null, $this->admin);
    }

    private function correctAt(string $when, WasteEntry $entry, string $quantity): WasteEntry
    {
        $this->travelTo(CarbonImmutable::parse($when));

        return app(CorrectWasteEntry::class)->execute($entry, $quantity, $this->admin);
    }

    /**
     * Known data, "today" = 13/11/2026. Last 7 days = 07/11 → 13/11, previous = 31/10 → 06/11.
     * In the period: tomato 2 kg (17,00) + 1 kg (8,50), beef 0,5 kg (20,00), milk 3 L corrected
     * to 1 L (5,00) → 50,50. Previous period: beef 1 kg (40,00). Another restaurant: ignored.
     */
    private function seedKnownWeek(): void
    {
        $this->recordAt('2026-11-01 12:00', $this->beef, '1', WasteReason::Spoiled);
        $this->recordAt('2026-11-10 10:00', $this->tomato, '2', WasteReason::Spoiled);
        $this->recordAt('2026-11-11 12:00', $this->beef, '0.5', WasteReason::Expired);
        $this->recordAt('2026-11-12 09:00', $this->tomato, '1', WasteReason::Overproduction);
        $milk = $this->recordAt('2026-11-12 09:30', $this->milk, '3', WasteReason::Expired);
        $this->correctAt('2026-11-13 10:00', $milk, '1');

        // The tomato price changes later: the report keeps the cost of the day of the discard.
        $this->tomato->update(['cost_per_unit' => 20.00]);

        $other = Restaurant::factory()->create();
        $otherTomato = $this->ingredient($other, 'Tomate', IngredientUnit::Kilogram, IngredientCategory::Produce, 8.50);
        $this->recordAt('2026-11-12 11:00', $otherTomato, '5', WasteReason::Spoiled);

        $this->travelTo(CarbonImmutable::parse('2026-11-13 15:00'));
    }

    private function report(string $from, string $to, ?int $restaurantId = null): array
    {
        return app(BuildWasteReport::class)->execute(
            $restaurantId ?? $this->restaurant->id,
            CarbonImmutable::parse($from),
            CarbonImmutable::parse($to),
        );
    }

    public function test_totals_by_period_ingredient_category_and_reason_on_known_data(): void
    {
        $this->seedKnownWeek();

        $report = $this->report('2026-11-07', '2026-11-13');

        $this->assertSame(50.50, $report['total']);
        $this->assertSame(40.00, $report['previous_total']);
        $this->assertSame('2026-10-31', $report['previous_from']->toDateString());
        $this->assertSame('2026-11-06', $report['previous_to']->toDateString());

        $this->assertSame(
            [['Tomate', 3.0, 'kg', 25.50], ['Carne moída', 0.5, 'kg', 20.00], ['Leite', 1.0, 'L', 5.00]],
            array_map(fn ($row) => [$row['label'], $row['quantity'], $row['unit'], $row['cost']], $report['by_ingredient'])
        );
        $this->assertSame(
            [['Hortifrúti', 25.50], ['Carnes', 20.00], ['Laticínios', 5.00]],
            array_map(fn ($row) => [$row['label'], $row['cost']], $report['by_category'])
        );
        $this->assertSame(
            [['Vencido', 25.00], ['Estragado', 17.00], ['Sobra de produção', 8.50]],
            array_map(fn ($row) => [$row['label'], $row['cost']], $report['by_reason'])
        );

        // Share of the period total, for the bars.
        $this->assertEqualsWithDelta(50.5, $report['by_ingredient'][0]['share'], 0.1);
    }

    public function test_only_the_given_restaurant_is_counted(): void
    {
        $this->seedKnownWeek();

        $other = Restaurant::query()->where('id', '!=', $this->restaurant->id)->sole();
        $report = $this->report('2026-11-07', '2026-11-13', $other->id);

        $this->assertSame(42.50, $report['total']);
        $this->assertCount(1, $report['by_ingredient']);
        $this->assertSame(0.0, $report['previous_total']);
    }

    public function test_period_bounds_are_whole_days(): void
    {
        $this->recordAt('2026-11-06 23:59:59', $this->tomato, '1', WasteReason::Spoiled);
        $this->recordAt('2026-11-07 00:00:00', $this->tomato, '2', WasteReason::Spoiled);
        $this->recordAt('2026-11-09 23:59:59', $this->tomato, '4', WasteReason::Spoiled);
        $this->recordAt('2026-11-10 00:00:00', $this->tomato, '8', WasteReason::Spoiled);

        $report = $this->report('2026-11-07', '2026-11-09');

        $this->assertSame(51.00, $report['total']); // (2 + 4) kg × 8,50
        $this->assertSame(8.50, $report['previous_total']); // 04/11 → 06/11
    }

    public function test_a_correction_counts_in_the_period_of_the_entry_it_corrects(): void
    {
        $beef = $this->recordAt('2026-11-01 12:00', $this->beef, '1', WasteReason::Spoiled);
        $this->recordAt('2026-11-10 12:00', $this->tomato, '2', WasteReason::Spoiled);
        // Corrected this week, but the discard happened on 01/11.
        $this->correctAt('2026-11-12 08:00', $beef, '0.5');

        $report = $this->report('2026-11-07', '2026-11-13');

        $this->assertSame(17.00, $report['total']);
        $this->assertSame(20.00, $report['previous_total']);
        $this->assertSame(['Tomate'], array_column($report['by_ingredient'], 'label'));
    }

    public function test_an_entry_corrected_to_zero_leaves_the_rankings(): void
    {
        $milk = $this->recordAt('2026-11-10 12:00', $this->milk, '2', WasteReason::Other);
        $this->correctAt('2026-11-10 12:05', $milk, '0');
        $this->recordAt('2026-11-10 13:00', $this->tomato, '1', WasteReason::Spoiled);

        $report = $this->report('2026-11-07', '2026-11-13');

        $this->assertSame(8.50, $report['total']);
        $this->assertSame(['Tomate'], array_column($report['by_ingredient'], 'label'));
        $this->assertSame(['Hortifrúti'], array_column($report['by_category'], 'label'));
        $this->assertSame(['Estragado'], array_column($report['by_reason'], 'label'));
    }

    public function test_admin_and_manager_open_the_report_on_the_real_route(): void
    {
        $this->seedKnownWeek();

        foreach ([UserRole::Admin, UserRole::Manager] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('admin.reports.waste'))
                ->assertOk()
                ->assertDontSee('Undefined')
                ->assertSee('Relatório de desperdício')
                ->assertSee('R$ 50,50')
                ->assertSee('R$ 40,00')
                ->assertSee('Carne moída')
                ->assertSee('Hortifrúti')
                ->assertSee('Sobra de produção');
        }
    }

    public function test_empty_period_shows_a_message(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-13 15:00'));

        $this->actingAs($this->admin)
            ->get(route('admin.reports.waste'))
            ->assertOk()
            ->assertSee('Nenhum descarte registrado neste período.');
    }

    public function test_kitchen_attendant_and_delivery_cannot_open_the_report(): void
    {
        foreach ([UserRole::Kitchen, UserRole::Attendant, UserRole::Delivery] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('admin.reports.waste'))->assertForbidden();

            // Livewire actions do not go through the route middleware again: the component checks the role too.
            Volt::test('reports.waste')->assertForbidden();
        }
    }

    public function test_period_presets_set_the_range(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-13 15:00'));
        $this->actingAs($this->admin);

        $component = Volt::test('reports.waste')
            ->assertSet('period', '7d')
            ->assertSet('rangeFrom', '2026-11-07')
            ->assertSet('rangeTo', '2026-11-13');

        $component->call('setPeriod', '30d')
            ->assertSet('rangeFrom', '2026-10-15')
            ->assertSet('rangeTo', '2026-11-13');

        $component->call('setPeriod', 'month')
            ->assertSet('rangeFrom', '2026-11-01')
            ->assertSet('rangeTo', '2026-11-13');

        $component->call('setPeriod', 'last_month')
            ->assertSet('rangeFrom', '2026-10-01')
            ->assertSet('rangeTo', '2026-10-31');

        // Unknown preset: back to the default.
        $component->call('setPeriod', 'forever')
            ->assertSet('period', '7d')
            ->assertSet('rangeFrom', '2026-11-07');
    }

    public function test_custom_period_is_validated_and_applied(): void
    {
        $this->seedKnownWeek();
        $this->actingAs($this->admin);

        Volt::test('reports.waste')
            ->set('from', '2026-11-12')
            ->set('to', '2026-11-10')
            ->call('applyCustom')
            ->assertHasErrors('to')
            ->assertSet('rangeFrom', '2026-11-07')
            ->set('from', '2025-01-01')
            ->set('to', '2026-11-10')
            ->call('applyCustom')
            ->assertHasErrors('to')
            ->set('from', '2026-11-12')
            ->set('to', '2026-11-12')
            ->call('applyCustom')
            ->assertHasNoErrors()
            ->assertSet('period', 'custom')
            ->assertSet('rangeFrom', '2026-11-12')
            ->assertSee('R$ 13,50'); // tomato 1 kg (8,50) + milk 1 L after correction (5,00)
    }

    public function test_applied_range_cannot_be_changed_from_the_browser(): void
    {
        $this->actingAs($this->admin);

        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

        Volt::test('reports.waste')->set('rangeFrom', '2000-01-01');
    }

    public function test_reports_and_waste_screens_link_to_the_report_for_admin_and_manager_only(): void
    {
        foreach ([UserRole::Admin, UserRole::Manager] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('admin.reports.index'))->assertOk()->assertSee(route('admin.reports.waste'), false);
            $this->actingAs($user)->get(route('admin.waste.index'))->assertOk()->assertSee(route('admin.reports.waste'), false);
        }

        $kitchen = User::factory()->create(['role' => UserRole::Kitchen]);
        $this->actingAs($kitchen)->get(route('admin.waste.index'))->assertOk()->assertDontSee(route('admin.reports.waste'), false);
        $this->actingAs($kitchen)->get(route('admin.reports.index'))->assertOk()->assertDontSee(route('admin.reports.waste'), false);
    }
}
