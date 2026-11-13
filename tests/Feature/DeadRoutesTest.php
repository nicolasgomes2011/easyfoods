<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Leftover storefront stubs, the customer account area (no customer login
 * exists) and starter-kit views were removed. Every remaining public and
 * admin screen must still render.
 */
class DeadRoutesTest extends TestCase
{
    use RefreshDatabase;

    public static function removedRouteNames(): array
    {
        return [
            'product stub'       => ['store.product'],
            'account orders'     => ['account.orders'],
            'account profile'    => ['account.profile'],
            'account addresses'  => ['account.addresses'],
        ];
    }

    #[DataProvider('removedRouteNames')]
    public function test_route_pointing_to_a_stub_is_gone(string $name): void
    {
        $this->assertFalse(Route::has($name), "Route [{$name}] should not be registered.");
    }

    public static function removedUrls(): array
    {
        return [
            'product stub'      => ['/store/product/x-burger'],
            'account orders'    => ['/account/orders'],
            'account profile'   => ['/account/profile'],
            'account addresses' => ['/account/addresses'],
        ];
    }

    #[DataProvider('removedUrls')]
    public function test_removed_url_responds_404(string $url): void
    {
        $this->get($url)->assertNotFound();
    }

    public static function removedViews(): array
    {
        // File paths, not view names: `store.menu` etc. still resolve to the
        // real Volt components under resources/views/livewire/store.
        return [
            ['store/menu.blade.php'],
            ['store/cart.blade.php'],
            ['store/checkout.blade.php'],
            ['store/order-tracking.blade.php'],
            ['store/product.blade.php'],
            ['store/account/orders.blade.php'],
            ['store/account/profile.blade.php'],
            ['store/account/addresses.blade.php'],
            ['admin/orders/index.blade.php'],
            ['admin/orders/show.blade.php'],
            ['admin/orders/history.blade.php'],
            ['admin/orders/in-progress.blade.php'],
            ['livewire/auth/register.blade.php'],
            ['livewire/auth/login.blade.php'],
            ['welcome.blade.php'],
        ];
    }

    #[DataProvider('removedViews')]
    public function test_unused_view_was_removed(string $path): void
    {
        $this->assertFileDoesNotExist(resource_path('views/'.$path));
    }

    public function test_storefront_header_has_no_staff_login_or_account_link(): void
    {
        Restaurant::factory()->create();

        $this->get(route('store.home'))
            ->assertOk()
            ->assertDontSee('Entrar')
            ->assertDontSee('Meus pedidos')
            ->assertDontSee(route('login'), false);
    }

    public static function publicRoutes(): array
    {
        return [
            'store home' => ['store.home'],
            'store menu' => ['store.menu'],
            'store cart' => ['store.cart'],
            'login'      => ['login'],
        ];
    }

    #[DataProvider('publicRoutes')]
    public function test_public_screen_renders(string $name): void
    {
        Restaurant::factory()->create();

        $this->get(route($name))->assertOk();
    }

    public static function adminRoutes(): array
    {
        return [
            ['admin.dashboard'],
            ['admin.orders.index'],
            ['admin.orders.in-progress'],
            ['admin.orders.history'],
            ['admin.kitchen.index'],
            ['admin.driver.index'],
            ['admin.dining.tables'],
            ['admin.dining.queue'],
            ['admin.catalog.products'],
            ['admin.catalog.products.create'],
            ['admin.catalog.categories'],
            ['admin.catalog.addons'],
            ['admin.customers.index'],
            ['admin.reports.index'],
            ['admin.settings.store'],
            ['admin.settings.hours'],
            ['admin.settings.delivery'],
            ['admin.settings.payments'],
            ['admin.users.index'],
        ];
    }

    #[DataProvider('adminRoutes')]
    public function test_admin_screen_renders(string $name): void
    {
        Restaurant::factory()->create();
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

        $this->get(route($name))->assertOk();
    }
}
