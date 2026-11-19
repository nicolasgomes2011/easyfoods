<?php

namespace Tests\Feature\Auth;

use App\Enums\DiningTableStatus;
use App\Enums\UserRole;
use App\Enums\WaiterCallStatus;
use App\Models\DiningTable;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\WaiterCall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Livewire actions go through the role matrix again (EF-63): a request to
 * the Livewire update endpoint re-runs the RoleMiddleware of the page the
 * component was rendered on. A user whose role no longer opens that area
 * (e.g. the admin changed it while the page was open) gets 403 and nothing
 * changes. These tests use real HTTP requests, because Livewire::test()
 * skips the persistent middleware.
 */
class LivewireActionRoleTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
    }

    private function staff(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'restaurant_id' => $this->restaurant->id]);
    }

    private function table(): DiningTable
    {
        return DiningTable::create([
            'restaurant_id' => $this->restaurant->id,
            'number'        => '3',
            'capacity'      => 4,
            'status'        => DiningTableStatus::Free,
        ]);
    }

    private function pendingCall(): WaiterCall
    {
        return WaiterCall::create([
            'restaurant_id'   => $this->restaurant->id,
            'dining_table_id' => $this->table()->id,
            'status'          => WaiterCallStatus::Pending,
            'called_at'       => now(),
        ]);
    }

    /** Snapshot of the page's component whose name ends with $name, as the browser holds it. */
    private function snapshotFrom(TestResponse $page, string $name): string
    {
        preg_match_all('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches);

        $names = [];
        foreach ($matches[1] as $raw) {
            $json = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5);
            $componentName = json_decode($json, true)['memo']['name'] ?? '';
            $names[] = $componentName;

            if (str_ends_with($componentName, $name)) {
                return $json;
            }
        }

        $this->fail("No Livewire snapshot for {$name} on the page (found: ".implode(', ', $names).').');
    }

    /** Calls a component method the way the Livewire JS client does. */
    private function callAction(string $snapshot, string $method, array $params = []): TestResponse
    {
        return $this->withHeaders(['X-Livewire' => 'true'])
            ->postJson(app('livewire')->getUpdateUri(), [
                'components' => [[
                    'snapshot' => $snapshot,
                    'updates'  => [],
                    'calls'    => [['path' => '', 'method' => $method, 'params' => $params]],
                ]],
            ]);
    }

    public function test_kitchen_action_still_works_for_a_role_that_opens_the_kitchen(): void
    {
        $call = $this->pendingCall();
        $cook = $this->staff(UserRole::Kitchen);

        $page = $this->actingAs($cook)->get(route('admin.kitchen.index'))->assertOk();
        $snapshot = $this->snapshotFrom($page, 'kitchen.index');

        $this->callAction($snapshot, 'acknowledgeWaiterCall', [$call->id])->assertOk();

        $this->assertSame(WaiterCallStatus::Acknowledged, $call->fresh()->status);
    }

    public function test_driver_cannot_answer_a_waiter_call_through_the_livewire_endpoint(): void
    {
        $call = $this->pendingCall();
        $user = $this->staff(UserRole::Kitchen);

        $page = $this->actingAs($user)->get(route('admin.kitchen.index'))->assertOk();
        $snapshot = $this->snapshotFrom($page, 'kitchen.index');

        // The admin turns this user into a driver while the kitchen page is open.
        $user->forceFill(['role' => UserRole::Delivery])->save();

        $this->callAction($snapshot, 'acknowledgeWaiterCall', [$call->id])->assertForbidden();

        $this->assertSame(WaiterCallStatus::Pending, $call->fresh()->status);
    }

    public function test_dining_action_is_refused_once_the_role_loses_the_dining_room(): void
    {
        $table = $this->table();
        $user = $this->staff(UserRole::Attendant);

        $page = $this->actingAs($user)->get(route('admin.dining.tables'))->assertOk();
        $snapshot = $this->snapshotFrom($page, 'dining.tables');

        $user->forceFill(['role' => UserRole::Kitchen])->save();

        $this->callAction($snapshot, 'setStatus', [$table->id, 'occupied'])->assertForbidden();

        $this->assertSame(DiningTableStatus::Free, $table->fresh()->status);
    }
}
