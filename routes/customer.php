<?php

use Illuminate\Support\Facades\Route;

// Public storefront — no auth required
Route::prefix('store')->name('store.')->group(function () {
    Route::get('/',                        fn () => view('store.home'))->name('home');
    Route::get('/menu',                    fn () => view('store.menu'))->name('menu');
    Route::get('/menu/{category:slug}',    fn () => view('store.menu'))->name('menu.category');
    Route::get('/product/{product:slug}',  fn () => view('store.product'))->name('product');
    Route::get('/cart',                    fn () => view('store.cart'))->name('cart');
});

// Checkout + order tracking — auth optional (session-based for guests)
Route::prefix('store')->name('store.')->group(function () {
    Route::get('/checkout',                fn () => view('store.checkout'))->name('checkout');
    Route::get('/order/{order:number}',    fn () => view('store.order-tracking'))->name('order.tracking');
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
