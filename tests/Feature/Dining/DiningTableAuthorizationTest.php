<?php

namespace Tests\Feature\Dining;

use App\Enums\DiningTableStatus;
use App\Enums\UserRole;
use App\Enums\WaitlistStatus;
use App\Models\DiningTable;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Authorization inside the dining room screens (EF-63): the route lets admin,
 * manager and attendant in, but only admin and manager create, edit or delete
 * a table. The attendant still runs the room (status, close tab, waitlist).
 * Each check lives in the component, so it holds even without the route.
 */
class DiningTableAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
    }

    private function actAs(UserRole $role): User
    {
        $user = User::factory()->create(['role' => $role, 'restaurant_id' => $this->restaurant->id]);
        $this->actingAs($user);

        return $user;
    }

    private function table(int $number = 1, DiningTableStatus $status = DiningTableStatus::Free): DiningTable
    {
        return DiningTable::create([
            'restaurant_id' => $this->restaurant->id,
            'number'        => (string) $number,
            'capacity'      => 4,
            'status'        => $status,
        ]);
    }

    public function test_attendant_cannot_create_a_table(): void
    {
        $this->actAs(UserRole::Attendant);

        Volt::test('dining.tables')->call('openCreate')->assertForbidden();

        Volt::test('dining.tables')
            ->set('number', '9')
            ->set('capacity', 2)
            ->call('save')
            ->assertForbidden();

        $this->assertSame(0, DiningTable::count());
    }

    public function test_attendant_cannot_delete_a_table(): void
    {
        $table = $this->table();
        $this->actAs(UserRole::Attendant);

        Volt::test('dining.tables')->call('delete', $table->id)->assertForbidden();

        $this->assertDatabaseHas('dining_tables', ['id' => $table->id]);
    }

    public function test_attendant_cannot_edit_a_table(): void
    {
        $table = $this->table(1);
        $this->actAs(UserRole::Attendant);

        Volt::test('dining.tables')->call('openEdit', $table->id)->assertForbidden();

        Volt::test('dining.tables')
            ->set('editingId', (string) $table->id)
            ->set('number', '99')
            ->set('capacity', 8)
            ->call('save')
            ->assertForbidden();

        $this->assertSame('1', $table->fresh()->number);
        $this->assertSame(4, $table->fresh()->capacity);
    }

    public function test_attendant_still_operates_the_room(): void
    {
        $table = $this->table();
        $this->actAs(UserRole::Attendant);

        Volt::test('dining.tables')
            ->call('setStatus', $table->id, 'reserved')
            ->assertHasNoErrors()
            ->assertOk();

        $this->assertSame(DiningTableStatus::Reserved, $table->fresh()->status);
    }

    public function test_attendant_does_not_see_the_manage_buttons(): void
    {
        $this->table();
        $this->actAs(UserRole::Attendant);

        $this->get(route('admin.dining.tables'))
            ->assertOk()
            ->assertDontSee('+ Nova mesa')
            ->assertDontSee('wire:click="openEdit(', false)
            ->assertDontSee('wire:click="delete(', false)
            ->assertSee('Reservar');
    }

    public static function managers(): array
    {
        return [
            'admin'   => [UserRole::Admin],
            'manager' => [UserRole::Manager],
        ];
    }

    #[DataProvider('managers')]
    public function test_admin_and_manager_create_edit_and_delete_tables(UserRole $role): void
    {
        $this->actAs($role);

        Volt::test('dining.tables')
            ->call('openCreate')
            ->set('number', '7')
            ->set('capacity', 6)
            ->call('save')
            ->assertHasNoErrors();

        $table = DiningTable::where('number', '7')->firstOrFail();

        Volt::test('dining.tables')
            ->call('openEdit', $table->id)
            ->set('capacity', 2)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, $table->fresh()->capacity);

        Volt::test('dining.tables')->call('delete', $table->id)->assertHasNoErrors();

        $this->assertDatabaseMissing('dining_tables', ['id' => $table->id]);

        $this->get(route('admin.dining.tables'))->assertOk()->assertSee('+ Nova mesa');
    }

    public static function rolesWithoutTheDiningRoom(): array
    {
        return [
            'kitchen'  => [UserRole::Kitchen],
            'delivery' => [UserRole::Delivery],
        ];
    }

    #[DataProvider('rolesWithoutTheDiningRoom')]
    public function test_roles_without_the_dining_room_cannot_operate_tables(UserRole $role): void
    {
        $table = $this->table();
        $this->actAs($role);

        Volt::test('dining.tables')->call('setStatus', $table->id, 'occupied')->assertForbidden();
        Volt::test('dining.tables')->call('closeSession', $table->id)->assertForbidden();

        $this->assertSame(DiningTableStatus::Free, $table->fresh()->status);
    }

    #[DataProvider('rolesWithoutTheDiningRoom')]
    public function test_roles_without_the_dining_room_cannot_use_the_waitlist(UserRole $role): void
    {
        $entry = WaitlistEntry::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $this->actAs($role);

        Volt::test('dining.queue')
            ->set('partyName', 'Ana')
            ->set('partySize', '2')
            ->call('add')
            ->assertForbidden();

        Volt::test('dining.queue')->call('seat', $entry->id)->assertForbidden();
        Volt::test('dining.queue')->call('remove', $entry->id)->assertForbidden();

        $this->assertSame(1, WaitlistEntry::count());
        $this->assertSame(WaitlistStatus::Waiting, $entry->fresh()->status);
    }

    public function test_attendant_uses_the_waitlist(): void
    {
        $this->actAs(UserRole::Attendant);

        Volt::test('dining.queue')
            ->set('partyName', 'Ana')
            ->set('partySize', '2')
            ->call('add')
            ->assertHasNoErrors();

        $entry = WaitlistEntry::firstOrFail();

        Volt::test('dining.queue')->call('seat', $entry->id)->assertHasNoErrors();

        $this->assertSame(WaitlistStatus::Seated, $entry->fresh()->status);
    }
}
