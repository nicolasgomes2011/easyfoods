<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Livewire\Auth\LoginForm;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Role access matrix (EF-10): the sidebar only shows the areas a role can
 * open, every visible link answers 200, every hidden one answers 403, and
 * each role lands on its own home screen after login.
 */
class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    /** Sidebar links per area of the matrix. */
    private const AREA_ROUTES = [
        'dashboard' => ['admin.dashboard'],
        'orders'    => ['admin.orders.index', 'admin.orders.in-progress', 'admin.orders.history'],
        'dining'    => ['admin.dining.tables', 'admin.dining.queue'],
        'kitchen'   => ['admin.kitchen.index'],
        'driver'    => ['admin.driver.index'],
        'catalog'   => ['admin.catalog.products', 'admin.catalog.categories', 'admin.catalog.addons'],
        'customers' => ['admin.customers.index'],
        'reports'   => ['admin.reports.index'],
        'settings'  => ['admin.settings.store', 'admin.settings.hours', 'admin.settings.delivery'],
        'users'     => ['admin.users.index'],
    ];

    /** Expected matrix: area => roles that may open it. */
    private const MATRIX = [
        'dashboard' => ['admin', 'manager', 'attendant'],
        'orders'    => ['admin', 'manager', 'attendant'],
        'dining'    => ['admin', 'manager', 'attendant'],
        'kitchen'   => ['admin', 'manager', 'attendant', 'kitchen'],
        'driver'    => ['admin', 'manager', 'delivery'],
        'catalog'   => ['admin', 'manager'],
        'customers' => ['admin', 'manager', 'attendant'],
        'reports'   => ['admin', 'manager'],
        'settings'  => ['admin', 'manager'],
        'users'     => ['admin'],
    ];

    public static function roles(): array
    {
        return [
            'admin'     => [UserRole::Admin, 'admin.dashboard'],
            'manager'   => [UserRole::Manager, 'admin.dashboard'],
            'attendant' => [UserRole::Attendant, 'admin.dashboard'],
            'kitchen'   => [UserRole::Kitchen, 'admin.kitchen.index'],
            'delivery'  => [UserRole::Delivery, 'admin.driver.index'],
        ];
    }

    #[DataProvider('roles')]
    public function test_menu_shows_only_the_areas_the_role_can_open(UserRole $role, string $home): void
    {
        Restaurant::factory()->create();
        $this->actingAs(User::factory()->create(['role' => $role]));

        $page = $this->get(route($home))->assertOk();

        foreach (self::AREA_ROUTES as $area => $routes) {
            $allowed = in_array($role->value, self::MATRIX[$area], true);
            $this->assertSame($allowed, $role->canAccess($area), "matrix mismatch: {$role->value} / {$area}");

            foreach ($routes as $name) {
                $link = 'href="' . route($name) . '"';

                if ($allowed) {
                    $page->assertSee($link, false);
                    $this->get(route($name))->assertOk();
                } else {
                    $page->assertDontSee($link, false);
                    $this->get(route($name))->assertForbidden();
                }
            }
        }
    }

    #[DataProvider('roles')]
    public function test_login_lands_on_the_role_home_screen(UserRole $role, string $home): void
    {
        $user = User::factory()->create(['role' => $role]);

        Livewire::test(LoginForm::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route($home, absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    #[DataProvider('roles')]
    public function test_legacy_dashboard_and_admin_root_redirect_to_the_role_home(UserRole $role, string $home): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));

        $this->get('/dashboard')->assertRedirect(route($home));
        $this->get('/admin')->assertRedirect(route($home));
    }

    public function test_kitchen_staff_logs_in_straight_to_the_kitchen_queue(): void
    {
        Restaurant::factory()->create();
        $user = User::factory()->create(['role' => UserRole::Kitchen]);

        Livewire::test(LoginForm::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('admin.kitchen.index', absolute: false));

        $this->get(route('admin.kitchen.index'))->assertOk();
        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_deactivated_account_cannot_log_in(): void
    {
        $user = User::factory()->create(['role' => UserRole::Attendant, 'is_active' => false]);

        Livewire::test(LoginForm::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors(['email'])
            ->assertSee('Sua conta foi desativada.');

        $this->assertGuest();
    }

    public function test_deactivated_account_with_wrong_password_gets_the_generic_error(): void
    {
        $user = User::factory()->create(['role' => UserRole::Attendant, 'is_active' => false]);

        Livewire::test(LoginForm::class)
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors(['email'])
            ->assertDontSee('Sua conta foi desativada.');

        $this->assertGuest();
    }

    public function test_deactivated_session_is_logged_out_on_account_pages(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager, 'is_active' => false]));

        $this->get('/settings/profile')->assertRedirect(route('login'));
        $this->assertGuest();

        $this->actingAs(User::factory()->create(['role' => UserRole::Manager, 'is_active' => false]));

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
