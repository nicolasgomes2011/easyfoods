<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth', 'role:admin,manager,attendant,kitchen,delivery'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/', fn () => redirect()->route('dashboard'));

        // ── PEDIDOS ──────────────────────────────────────────────────────────
        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('/',             fn () => view('admin.orders.index'))->name('index');
            Route::get('/in-progress',  fn () => view('admin.orders.in-progress'))->name('in-progress');
            Route::get('/history',      fn () => view('admin.orders.history'))->name('history');
            Route::get('/{order}',      fn () => view('admin.orders.show'))->name('show');
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
            Route::get('/products',           fn () => view('admin.catalog.products'))->name('products');
            Route::get('/products/{product}', fn () => view('admin.catalog.product-edit'))->name('products.edit');
            Route::get('/categories',         fn () => view('admin.catalog.categories'))->name('categories');
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
            Route::get('/',         fn () => redirect()->route('admin.settings.store'))->name('index');
            Route::get('/store',    fn () => view('admin.settings.store'))->name('store');
            Route::get('/hours',    fn () => view('admin.settings.hours'))->name('hours');
            Route::get('/delivery', fn () => view('admin.settings.delivery'))->name('delivery');
            Route::get('/payments', fn () => view('admin.settings.payments'))->name('payments');
        });

        // ── USUÁRIOS — admin only ─────────────────────────────────────────────
        Route::middleware('role:admin')->prefix('users')->name('users.')->group(function () {
            Route::get('/', fn () => view('admin.users.index'))->name('index');
        });

    });
