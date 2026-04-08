<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:admin,manager,attendant,kitchen,delivery'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/', fn () => redirect()->route('admin.dashboard'))->name('index');
        Route::get('/dashboard', fn () => view('admin.dashboard'))->name('dashboard');

        // Orders
        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('/',        fn () => view('admin.orders.index'))->name('index');
            Route::get('/{order}', fn () => view('admin.orders.show'))->name('show');
        });

        // Catalog — restricted to admin/manager
        Route::middleware('role:admin,manager')->group(function () {
            Route::prefix('catalog')->name('catalog.')->group(function () {
                Route::get('/categories',          fn () => view('admin.catalog.categories'))->name('categories');
                Route::get('/products',            fn () => view('admin.catalog.products'))->name('products');
                Route::get('/products/{product}',  fn () => view('admin.catalog.product-edit'))->name('products.edit');
            });
        });

        // Settings — restricted to admin/manager
        Route::middleware('role:admin,manager')->prefix('settings')->name('settings.')->group(function () {
            Route::get('/',         fn () => redirect()->route('admin.settings.store'))->name('index');
            Route::get('/store',    fn () => view('admin.settings.store'))->name('store');
            Route::get('/hours',    fn () => view('admin.settings.hours'))->name('hours');
            Route::get('/delivery', fn () => view('admin.settings.delivery'))->name('delivery');
            Route::get('/payments', fn () => view('admin.settings.payments'))->name('payments');
        });

        // Users — admin only
        Route::middleware('role:admin')->prefix('users')->name('users.')->group(function () {
            Route::get('/', fn () => view('admin.users.index'))->name('index');
        });

    });
