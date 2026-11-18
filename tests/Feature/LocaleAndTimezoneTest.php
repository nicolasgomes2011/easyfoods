<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Livewire\Dashboard;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

/**
 * The platform runs for restaurants in Brazil: "today" follows Brasília time
 * and the framework messages (validation, auth, passwords) are in Portuguese.
 */
class LocaleAndTimezoneTest extends TestCase
{
    use RefreshDatabase;

    /** A Brasília wall-clock moment, as the app's now() would return it. */
    private function brasilia(string $time): Carbon
    {
        return Carbon::parse($time, 'America/Sao_Paulo')->setTimezone(config('app.timezone'));
    }

    public function test_app_runs_in_brasilia_time_and_portuguese(): void
    {
        $this->assertSame('America/Sao_Paulo', config('app.timezone'));
        $this->assertSame('pt_BR', app()->getLocale());
    }

    public function test_order_placed_in_the_evening_counts_as_today_on_the_dashboard(): void
    {
        $restaurant = Restaurant::factory()->create();

        // 20:30 in Brasília is already the next day in UTC.
        $this->travelTo($this->brasilia('2026-11-06 20:30:00'));
        Order::factory()->for($restaurant)->create(['status' => OrderStatus::Confirmed]);

        $this->travelTo($this->brasilia('2026-11-06 22:00:00'));
        $this->actingAs(User::factory()->create());

        $this->assertSame(1, Livewire::test(Dashboard::class)->instance()->todayOrderCount);
    }

    public function test_order_from_the_previous_evening_does_not_count_as_today(): void
    {
        $restaurant = Restaurant::factory()->create();

        $this->travelTo($this->brasilia('2026-11-05 22:00:00'));
        Order::factory()->for($restaurant)->create(['status' => OrderStatus::Confirmed]);

        $this->travelTo($this->brasilia('2026-11-06 09:00:00'));
        $this->actingAs(User::factory()->create());

        $this->assertSame(0, Livewire::test(Dashboard::class)->instance()->todayOrderCount);
    }

    public function test_checkout_validation_errors_are_in_portuguese(): void
    {
        $restaurant = Restaurant::factory()->create(['accepts_delivery' => true]);
        $category = Category::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Lanches',
            'slug'          => 'lanches',
            'is_active'     => true,
            'sort_order'    => 1,
        ]);
        $product = Product::create([
            'restaurant_id'       => $restaurant->id,
            'category_id'         => $category->id,
            'name'                => 'X-Burger',
            'price'               => 25.00,
            'availability_status' => 'available',
            'sort_order'          => 1,
            'is_featured'         => false,
        ]);

        Volt::test('store.menu')
            ->call('openProduct', $product->id)
            ->call('addToCart', $product->id)
            ->assertHasNoErrors();

        $errors = Volt::test('store.checkout')
            ->set('deliveryType', 'delivery')
            ->set('customerName', '')
            ->set('street', '')
            ->set('addressNumber', '')
            ->call('placeOrder')
            ->assertHasErrors(['customerName', 'street', 'addressNumber'])
            ->errors();

        $this->assertSame('O campo nome é obrigatório.', $errors->first('customerName'));
        $this->assertSame('O campo logradouro é obrigatório.', $errors->first('street'));
        $this->assertSame('O campo número é obrigatório.', $errors->first('addressNumber'));
        $this->assertSame(0, Order::count());
    }

    public function test_auth_password_and_pagination_messages_are_translated(): void
    {
        foreach (['auth.failed', 'auth.throttle', 'passwords.reset', 'passwords.user', 'pagination.next'] as $key) {
            $this->assertNotSame($key, __($key), "{$key} has no pt_BR translation");
            $this->assertNotSame(__($key, [], 'en'), __($key), "{$key} is still in English");
        }

        $this->assertSame('Essas credenciais não correspondem aos nossos registros.', __('auth.failed'));
    }
}
