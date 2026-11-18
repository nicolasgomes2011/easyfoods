<?php

namespace App\Providers;

use App\Http\Middleware\RoleMiddleware;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\WaiterCall;
use App\Policies\DiningTablePolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Policies\UserPolicy;
use App\Policies\WaiterCallPolicy;
use App\Support\CurrentRestaurant;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentRestaurant::class);
    }

    public function boot(): void
    {
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(WaiterCall::class, WaiterCallPolicy::class);
        Gate::policy(DiningTable::class, DiningTablePolicy::class);

        // Livewire actions (the update endpoint) re-run the role matrix of the
        // page the component was rendered on, not only the first page load.
        Livewire::addPersistentMiddleware([RoleMiddleware::class]);
    }
}
