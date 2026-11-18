<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Livewire\Dashboard;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * Multi-tenant 1/4 (EF-81): every staff member belongs to a restaurant
 * (users.restaurant_id) and the panel works on the restaurant of the
 * logged-in user, not just on the lowest restaurant id.
 */
class StaffRestaurantTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_11_16_000000_add_restaurant_id_to_users_table.php';

    public function test_existing_staff_get_the_current_restaurant_and_the_migration_rolls_back(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            // MySQL commits implicitly on DDL, which would leak this test's rows past RefreshDatabase.
            $this->markTestSkipped('Re-running a migration inside the test transaction is only safe on SQLite.');
        }

        $migration = require database_path(self::MIGRATION);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'restaurant_id'));

        $current = Restaurant::factory()->create();
        Restaurant::factory()->create();
        $staff = User::factory()->create();

        $migration->up();

        $this->assertTrue(Schema::hasColumn('users', 'restaurant_id'));
        $this->assertSame($current->id, (int) DB::table('users')->where('id', $staff->id)->value('restaurant_id'));
    }

    public function test_a_user_of_restaurant_b_sees_only_b_on_the_dashboard(): void
    {
        $restaurantA = Restaurant::factory()->create(); // lowest id: the old default
        $restaurantB = Restaurant::factory()->create();

        Order::factory()->for($restaurantA)->count(3)->create(['status' => OrderStatus::PendingConfirmation]);
        Order::factory()->for($restaurantA)->inPreparation()->create();
        Order::factory()->for($restaurantB)->create(['status' => OrderStatus::PendingConfirmation]);

        $this->actingAs(User::factory()->create([
            'role'          => UserRole::Admin,
            'restaurant_id' => $restaurantB->id,
        ]));

        $dashboard = Livewire::test(Dashboard::class)->instance();
        $this->assertSame(1, $dashboard->openCount);
        $this->assertSame(0, $dashboard->inPreparationCount);
        $this->assertSame(1, $dashboard->todayOrderCount);

        $this->get(route('admin.dashboard'))->assertOk()->assertDontSee('Undefined');
    }

    public function test_an_invited_staff_member_starts_in_the_restaurant_of_who_invited(): void
    {
        Notification::fake();

        Restaurant::factory()->create(); // lowest id: the old default
        $restaurantB = Restaurant::factory()->create();

        $this->actingAs(User::factory()->create([
            'role'          => UserRole::Admin,
            'restaurant_id' => $restaurantB->id,
        ]));

        Volt::test('users.index')
            ->set('inviteName', 'Ana Cozinha')
            ->set('inviteEmail', 'ana@example.com')
            ->set('inviteRole', UserRole::Kitchen->value)
            ->call('invite')
            ->assertHasNoErrors()
            ->assertSet('inviteError', null);

        $invited = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertSame($restaurantB->id, $invited->restaurant_id);
        $this->assertTrue($invited->restaurant->is($restaurantB));
    }

    public function test_restaurant_id_is_not_mass_assignable(): void
    {
        $restaurant = Restaurant::factory()->create();

        $user = new User(['name' => 'X', 'email' => 'x@example.com', 'restaurant_id' => $restaurant->id]);

        $this->assertNull($user->restaurant_id);
    }
}
