<?php

use App\Models\Product;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Acesso por área: matriz única em \App\Enums\UserRole::allowedFor() (também usada pelo menu).
Route::middleware(['auth', 'role:admin,manager,attendant,kitchen,delivery'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/', fn () => redirect()->route(auth()->user()->homeRoute()));
        Route::get('/dashboard', \App\Livewire\Dashboard::class)->middleware(\App\Enums\UserRole::middleware('dashboard'))->name('dashboard');

        // ── PEDIDOS ──────────────────────────────────────────────────────────
        Route::middleware(\App\Enums\UserRole::middleware('orders'))->prefix('orders')->name('orders.')->group(function () {
            Volt::route('/',            'orders.index')->name('index');
            Volt::route('/in-progress', 'orders.in-progress')->name('in-progress');
            Volt::route('/history',     'orders.history')->name('history');
            Volt::route('/{order}',     'orders.show')->name('show');
        });

        // ── COZINHA ───────────────────────────────────────────────────────────
        Route::middleware(\App\Enums\UserRole::middleware('kitchen'))->prefix('kitchen')->name('kitchen.')->group(function () {
            Volt::route('/', 'kitchen.index')->name('index');
        });

        // ── ENTREGAS ─────────────────────────────────────────────────────────
        Route::middleware(\App\Enums\UserRole::middleware('driver'))->prefix('driver')->name('driver.')->group(function () {
            Volt::route('/', 'driver.index')->name('index');
        });

        // ── SALÃO ─────────────────────────────────────────────────────────────
        Route::middleware(\App\Enums\UserRole::middleware('dining'))->prefix('dining')->name('dining.')->group(function () {
            Route::get('/', fn () => redirect()->route('admin.dining.tables'));
            Volt::route('/tables', 'dining.tables')->name('tables');
            Volt::route('/queue',  'dining.queue')->name('queue');
        });

        // ── CARDÁPIO — admin/manager only ─────────────────────────────────────
        Route::middleware(\App\Enums\UserRole::middleware('catalog'))->prefix('catalog')->name('catalog.')->group(function () {
            Route::get('/products',                  fn () => view('admin.catalog.products'))->name('products');
            Route::get('/products/create',           fn () => view('admin.catalog.product-create'))->name('products.create');
            Route::get('/products/{product}',        fn (Product $product) => view('admin.catalog.product-edit', compact('product')))->name('products.edit');
            Volt::route('/categories',        'catalog.categories')->name('categories');
            Volt::route('/addons',            'catalog.addons')->name('addons');
            Volt::route('/ingredients',       'catalog.ingredients')->name('ingredients');
        });

        // ── CLIENTES ─────────────────────────────────────────────────────────
        Route::middleware(\App\Enums\UserRole::middleware('customers'))->prefix('customers')->name('customers.')->group(function () {
            Volt::route('/', 'customers.index')->name('index');
        });

        // ── RELATÓRIOS ───────────────────────────────────────────────────────
        Route::middleware(\App\Enums\UserRole::middleware('reports'))->prefix('reports')->name('reports.')->group(function () {
            Volt::route('/', 'reports.index')->name('index');
        });

        // ── CONFIGURAÇÕES — admin/manager only ───────────────────────────────
        Route::middleware(\App\Enums\UserRole::middleware('settings'))->prefix('settings')->name('settings.')->group(function () {
            Route::get('/', fn () => redirect()->route('admin.settings.store'))->name('index');
            Volt::route('/store',    'settings.store')->name('store');
            Volt::route('/hours',    'settings.hours')->name('hours');
            Volt::route('/delivery', 'settings.delivery')->name('delivery');
            Volt::route('/payments', 'settings.payments')->name('payments');
        });

        // ── USUÁRIOS — admin only ─────────────────────────────────────────────
        Route::middleware(\App\Enums\UserRole::middleware('users'))->prefix('users')->name('users.')->group(function () {
            Volt::route('/', 'users.index')->name('index');
        });

    });
