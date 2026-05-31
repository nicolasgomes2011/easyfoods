<?php

use App\Models\Product;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth', 'role:admin,manager,attendant,kitchen,delivery'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/', fn () => redirect()->route('admin.dashboard'));
        Route::get('/dashboard', \App\Livewire\Dashboard::class)->name('dashboard');

        // ── PEDIDOS ──────────────────────────────────────────────────────────
        Route::prefix('orders')->name('orders.')->group(function () {
            Volt::route('/',            'orders.index')->name('index');
            Volt::route('/in-progress', 'orders.in-progress')->name('in-progress');
            Volt::route('/history',     'orders.history')->name('history');
            Volt::route('/{order}',     'orders.show')->name('show');
        });

        // ── COZINHA ───────────────────────────────────────────────────────────
        Route::prefix('kitchen')->name('kitchen.')->group(function () {
            Volt::route('/', 'kitchen.index')->name('index');
        });

        // ── SALÃO ─────────────────────────────────────────────────────────────
        Route::prefix('dining')->name('dining.')->group(function () {
            Route::get('/', fn () => redirect()->route('admin.dining.tables'));
            Volt::route('/tables', 'dining.tables')->name('tables');
            Volt::route('/queue',  'dining.queue')->name('queue');
        });

        // ── CARDÁPIO — admin/manager only ─────────────────────────────────────
        Route::middleware('role:admin,manager')->prefix('catalog')->name('catalog.')->group(function () {
            Route::get('/products',                  fn () => view('admin.catalog.products'))->name('products');
            Route::get('/products/create',           fn () => view('admin.catalog.product-create'))->name('products.create');
            Route::get('/products/{product}',        fn (Product $product) => view('admin.catalog.product-edit', compact('product')))->name('products.edit');
            Volt::route('/categories',        'catalog.categories')->name('categories');
            Volt::route('/addons',            'catalog.addons')->name('addons');
        });

        // ── CLIENTES ─────────────────────────────────────────────────────────
        Route::prefix('customers')->name('customers.')->group(function () {
            Volt::route('/', 'customers.index')->name('index');
        });

        // ── RELATÓRIOS ───────────────────────────────────────────────────────
        Route::prefix('reports')->name('reports.')->group(function () {
            Volt::route('/', 'reports.index')->name('index');
        });

        // ── CONFIGURAÇÕES — admin/manager only ───────────────────────────────
        Route::middleware('role:admin,manager')->prefix('settings')->name('settings.')->group(function () {
            Route::get('/', fn () => redirect()->route('admin.settings.store'))->name('index');
            Volt::route('/store',    'settings.store')->name('store');
            Volt::route('/hours',    'settings.hours')->name('hours');
            Volt::route('/delivery', 'settings.delivery')->name('delivery');
            Volt::route('/payments', 'settings.payments')->name('payments');
        });

        // ── USUÁRIOS — admin only ─────────────────────────────────────────────
        Route::middleware('role:admin')->prefix('users')->name('users.')->group(function () {
            Route::get('/', fn () => view('admin.users.index'))->name('index');
        });

    });
