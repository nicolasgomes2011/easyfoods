<?php

namespace Tests\Feature\Waste;

use App\Actions\Waste\CorrectWasteEntry;
use App\Actions\Waste\RecordWaste;
use App\Enums\IngredientCategory;
use App\Enums\IngredientUnit;
use App\Enums\UserRole;
use App\Enums\WasteReason;
use App\Exceptions\WasteNotRecordable;
use App\Models\Ingredient;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\WasteEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Waste log (EF-40): the kitchen records what was thrown away (ingredient,
 * quantity, reason) in a few taps. Entries are append-only: the ingredient
 * name, unit and cost are snapshotted, nothing is deleted, and a correction
 * is a new adjustment entry. Scoped to the panel restaurant.
 */
class WasteEntriesTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private Ingredient $tomato;

    protected function setUp(): void
    {
        parent::setUp();

        // Lowest id: the restaurant the panel scopes to (CurrentRestaurant::id()).
        $this->restaurant = Restaurant::factory()->create();
        $this->tomato = Ingredient::factory()->for($this->restaurant)->create([
            'name'          => 'Tomate',
            'unit'          => IngredientUnit::Kilogram,
            'category'      => IngredientCategory::Produce,
            'cost_per_unit' => 8.50,
        ]);
    }

    private function user(UserRole $role = UserRole::Kitchen): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function record(string $quantity = '2', WasteReason $reason = WasteReason::Spoiled, ?User $by = null): WasteEntry
    {
        return app(RecordWaste::class)->execute($this->tomato, $quantity, $reason, null, $by ?? $this->user());
    }

    public function test_kitchen_admin_and_manager_open_the_page_on_the_real_route(): void
    {
        foreach ([UserRole::Kitchen, UserRole::Admin, UserRole::Manager] as $role) {
            $this->actingAs($this->user($role))
                ->get(route('admin.waste.index'))
                ->assertOk()
                ->assertDontSee('Undefined')
                ->assertSee('Desperdício')
                ->assertSee('Tomate')
                ->assertSee('Nenhum descarte registrado hoje.');
        }
    }

    public function test_attendant_and_delivery_cannot_open_the_page(): void
    {
        foreach ([UserRole::Attendant, UserRole::Delivery] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->get(route('admin.waste.index'))->assertForbidden();

            // Livewire actions do not go through the route middleware again: the component checks the role too.
            Volt::test('waste.index')->assertForbidden();
        }
    }

    public function test_sidebar_shows_the_waste_link_to_kitchen_but_not_to_attendant(): void
    {
        $this->actingAs($this->user(UserRole::Kitchen))
            ->get(route('admin.kitchen.index'))
            ->assertOk()
            ->assertSee(route('admin.waste.index'), false);

        $this->actingAs($this->user(UserRole::Attendant))
            ->get(route('admin.kitchen.index'))
            ->assertOk()
            ->assertDontSee(route('admin.waste.index'), false);
    }

    public function test_kitchen_records_a_discard_in_a_few_taps(): void
    {
        $kitchen = $this->user();
        $this->actingAs($kitchen);

        Volt::test('waste.index')
            ->call('selectIngredient', $this->tomato->id)
            ->set('quantity', '1.5')
            ->call('selectReason', WasteReason::Spoiled->value)
            ->call('record')
            ->assertHasNoErrors()
            ->assertSet('ingredientId', '')
            ->assertSet('quantity', '')
            ->assertSet('reason', '')
            ->assertSee('Descarte registrado: 1,5 kg de Tomate.');

        $entry = WasteEntry::sole();
        $this->assertSame($this->restaurant->id, $entry->restaurant_id);
        $this->assertSame($this->tomato->id, $entry->ingredient_id);
        $this->assertSame('Tomate', $entry->ingredient_name);
        $this->assertSame(IngredientUnit::Kilogram, $entry->unit);
        $this->assertSame('1.500', $entry->quantity);
        $this->assertSame(WasteReason::Spoiled, $entry->reason);
        $this->assertSame('8.5000', $entry->unit_cost);
        $this->assertSame($kitchen->id, $entry->recorded_by);
        $this->assertNull($entry->note);
        $this->assertNull($entry->adjusts_id);
        $this->assertNotNull($entry->created_at);

        $this->get(route('admin.waste.index'))
            ->assertOk()
            ->assertSeeHtml('data-waste-entry="'.$entry->id.'"')
            ->assertSee('1,5 kg')
            ->assertSee('Estragado')
            ->assertDontSee('Nenhum descarte registrado hoje.');
    }

    public function test_note_is_optional_and_saved_trimmed(): void
    {
        $this->actingAs($this->user());

        Volt::test('waste.index')
            ->call('selectIngredient', $this->tomato->id)
            ->set('quantity', '3')
            ->call('selectReason', WasteReason::Overproduction->value)
            ->set('note', '  sobrou do almoço  ')
            ->call('record')
            ->assertHasNoErrors();

        $this->assertSame('sobrou do almoço', WasteEntry::sole()->note);
    }

    public function test_unit_cost_is_kept_when_the_ingredient_price_changes(): void
    {
        $entry = $this->record('1.5');

        $this->tomato->update(['cost_per_unit' => 12.00]);

        $this->assertSame('8.5000', $entry->fresh()->unit_cost);

        // 1,5 kg × R$ 8,50 = R$ 12,75 (the new price would give R$ 18,00).
        $this->actingAs($this->user(UserRole::Manager))
            ->get(route('admin.waste.index'))
            ->assertOk()
            ->assertSee('R$ 12,75')
            ->assertDontSee('R$ 18,00');
    }

    public function test_kitchen_does_not_see_costs(): void
    {
        $this->record('1.5');

        $this->actingAs($this->user(UserRole::Kitchen))
            ->get(route('admin.waste.index'))
            ->assertOk()
            ->assertSee('1,5 kg')
            ->assertDontSee('R$ 12,75')
            ->assertDontSee('Total de hoje');
    }

    public function test_ingredient_quantity_and_reason_are_required(): void
    {
        $this->actingAs($this->user());

        Volt::test('waste.index')
            ->call('record')
            ->assertHasErrors(['ingredientId', 'quantity', 'reason']);

        Volt::test('waste.index')
            ->call('selectIngredient', $this->tomato->id)
            ->set('quantity', '0')
            ->call('selectReason', WasteReason::Expired->value)
            ->call('record')
            ->assertHasErrors(['quantity']);

        Volt::test('waste.index')
            ->call('selectIngredient', $this->tomato->id)
            ->set('quantity', '1')
            ->set('reason', 'stolen')
            ->call('record')
            ->assertHasErrors(['reason']);

        $this->assertSame(0, WasteEntry::count());
    }

    public function test_archived_ingredient_is_not_listed_and_cannot_be_recorded(): void
    {
        $archived = Ingredient::factory()->for($this->restaurant)->create([
            'name'        => 'Alface antiga',
            'archived_at' => now(),
        ]);
        $this->actingAs($this->user());

        $this->get(route('admin.waste.index'))->assertOk()->assertDontSee('Alface antiga');

        Volt::test('waste.index')
            ->set('ingredientId', (string) $archived->id)
            ->set('quantity', '1')
            ->call('selectReason', WasteReason::Expired->value)
            ->call('record')
            ->assertHasErrors(['ingredientId']);

        $this->assertSame(0, WasteEntry::count());

        $this->expectException(WasteNotRecordable::class);
        app(RecordWaste::class)->execute($archived, '1', WasteReason::Expired, null, $this->user());
    }

    public function test_ingredient_of_another_restaurant_cannot_be_used(): void
    {
        $foreign = Ingredient::factory()->for(Restaurant::factory()->create())->create(['name' => 'Picanha do vizinho']);
        $this->actingAs($this->user());

        $this->get(route('admin.waste.index'))->assertOk()->assertDontSee('Picanha do vizinho');

        Volt::test('waste.index')->call('selectIngredient', $foreign->id)->assertStatus(404);

        Volt::test('waste.index')
            ->set('ingredientId', (string) $foreign->id)
            ->set('quantity', '1')
            ->call('selectReason', WasteReason::Expired->value)
            ->call('record')
            ->assertStatus(404);

        $this->assertSame(0, WasteEntry::count());
    }

    public function test_entries_of_another_restaurant_are_not_listed_nor_correctable(): void
    {
        $foreign = WasteEntry::factory()->for(Restaurant::factory()->create())->create([
            'ingredient_name' => 'Picanha do vizinho',
        ]);
        $this->actingAs($this->user(UserRole::Manager));

        $this->get(route('admin.waste.index'))->assertOk()->assertDontSee('Picanha do vizinho');

        Volt::test('waste.index')->call('startCorrecting', $foreign->id)->assertStatus(404);

        Volt::test('waste.index')
            ->set('correctingId', (string) $foreign->id)
            ->set('correctQuantity', '0')
            ->call('saveCorrection')
            ->assertStatus(404);

        $this->assertSame(1, WasteEntry::count());
    }

    public function test_entries_from_previous_days_are_not_in_todays_list(): void
    {
        $this->travel(-1)->days();
        $this->record('4');
        $this->travelBack();

        $this->actingAs($this->user())
            ->get(route('admin.waste.index'))
            ->assertOk()
            ->assertDontSee('4 kg')
            ->assertSee('Nenhum descarte registrado hoje.');
    }

    public function test_entries_cannot_be_deleted_or_changed(): void
    {
        $entry = $this->record('2');

        $this->actingAs($this->user(UserRole::Admin))
            ->get(route('admin.waste.index'))
            ->assertOk()
            ->assertSee('Corrigir')
            ->assertDontSee('Excluir')
            ->assertDontSee('Apagar');

        try {
            $entry->delete();
            $this->fail('A waste entry was deleted.');
        } catch (LogicException) {
        }

        try {
            $entry->update(['quantity' => 1]);
            $this->fail('A waste entry was changed.');
        } catch (LogicException) {
        }

        $this->assertSame('2.000', WasteEntry::sole()->quantity);
    }

    public function test_correction_records_an_adjustment_and_keeps_the_original(): void
    {
        $original = $this->record('2');
        $manager = $this->user(UserRole::Manager);
        $this->actingAs($manager);

        Volt::test('waste.index')
            ->call('startCorrecting', $original->id)
            ->assertSet('correctQuantity', '2')
            ->set('correctQuantity', '1.5')
            ->call('saveCorrection')
            ->assertHasNoErrors()
            ->assertSet('correctingId', '')
            ->assertSee('Lançamento corrigido: Tomate agora soma 1,5 kg.');

        $this->assertSame(2, WasteEntry::count());
        $this->assertSame('2.000', $original->fresh()->quantity);

        $adjustment = WasteEntry::whereNotNull('adjusts_id')->sole();
        $this->assertSame($original->id, $adjustment->adjusts_id);
        $this->assertSame('-0.500', $adjustment->quantity);
        $this->assertSame('8.5000', $adjustment->unit_cost);
        $this->assertSame('Tomate', $adjustment->ingredient_name);
        $this->assertSame(WasteReason::Spoiled, $adjustment->reason);
        $this->assertSame($manager->id, $adjustment->recorded_by);
        $this->assertSame(1.5, $original->fresh()->effectiveQuantity());

        // 2 kg × 8,50 − 0,5 kg × 8,50 = R$ 12,75
        $this->get(route('admin.waste.index'))
            ->assertOk()
            ->assertSeeHtml('data-waste-adjustment="'.$adjustment->id.'"')
            ->assertSee('Correção')
            ->assertSee('Total de hoje: R$ 12,75');
    }

    public function test_correcting_to_zero_cancels_the_entry_in_the_totals(): void
    {
        $original = $this->record('2');

        app(CorrectWasteEntry::class)->execute($original, '0', $this->user(UserRole::Manager));

        $this->assertSame(0.0, $original->fresh()->effectiveQuantity());
        $this->assertSame('-2.000', WasteEntry::whereNotNull('adjusts_id')->sole()->quantity);
    }

    public function test_correction_to_the_same_quantity_or_of_an_adjustment_is_refused(): void
    {
        $original = $this->record('2');
        $this->actingAs($this->user());

        Volt::test('waste.index')
            ->call('startCorrecting', $original->id)
            ->set('correctQuantity', '2')
            ->call('saveCorrection')
            ->assertHasErrors(['correctQuantity']);

        Volt::test('waste.index')
            ->call('startCorrecting', $original->id)
            ->set('correctQuantity', '-1')
            ->call('saveCorrection')
            ->assertHasErrors(['correctQuantity']);

        $this->assertSame(1, WasteEntry::count());

        $adjustment = app(CorrectWasteEntry::class)->execute($original, '1', $this->user());

        $this->expectException(WasteNotRecordable::class);
        app(CorrectWasteEntry::class)->execute($adjustment, '0', $this->user());
    }
}
