<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Public storefront — no auth required
Route::prefix('store')->name('store.')->group(function () {
    Route::get('/',                      fn () => view('store.home'))->name('home');
    Volt::route('/menu',                 'store.menu')->name('menu');
    Volt::route('/menu/{category:slug}', 'store.menu')->name('menu.category');
    Volt::route('/cart',                 'store.cart')->name('cart');
});

// Checkout + order tracking — auth optional (session-based for guests)
Route::prefix('store')->name('store.')->group(function () {
    Volt::route('/checkout',             'store.checkout')->name('checkout');
    Volt::route('/order/{order}',        'store.order-tracking')->name('order.tracking');
});
