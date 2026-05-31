<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Public storefront — no auth required
Route::prefix('store')->name('store.')->group(function () {
    Route::get('/',                      fn () => view('store.home'))->name('home');
    Volt::route('/menu',                 'store.menu')->name('menu');
    Volt::route('/menu/{category:slug}', 'store.menu')->name('menu.category');
    Route::get('/product/{product:slug}', fn () => view('store.product'))->name('product');
    Volt::route('/cart',                 'store.cart')->name('cart');
});

// Checkout + order tracking — auth optional (session-based for guests)
Route::prefix('store')->name('store.')->group(function () {
    Volt::route('/checkout',             'store.checkout')->name('checkout');
    Volt::route('/order/{order}',        'store.order-tracking')->name('order.tracking');
});

// Customer account area
Route::middleware('auth:customer')
    ->prefix('account')
    ->name('account.')
    ->group(function () {
        Route::get('/orders',              fn () => view('store.account.orders'))->name('orders');
        Route::get('/profile',             fn () => view('store.account.profile'))->name('profile');
        Route::get('/addresses',           fn () => view('store.account.addresses'))->name('addresses');
    });
