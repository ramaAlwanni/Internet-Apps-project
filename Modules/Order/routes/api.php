<?php

use Illuminate\Support\Facades\Route;
use Modules\Order\Http\Controllers\CartController;
use Modules\Order\Http\Controllers\OrderController;

Route::middleware(['auth:api'])->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Cart Routes
    |--------------------------------------------------------------------------
    */

    Route::get('/cart', [
        CartController::class,
        'show',
    ]);

    Route::post('/cart/items', [
        CartController::class,
        'addItem',
    ]);

    Route::put('/cart/items/{product}', [
        CartController::class,
        'updateItem',
    ]);

    Route::delete('/cart/items/{product}', [
        CartController::class,
        'removeItem',
    ]);

    Route::delete('/cart', [
        CartController::class,
        'clear',
    ]);

    Route::post('/cart/checkout', [
        CartController::class,
        'checkout',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Order Routes
    |--------------------------------------------------------------------------
    */

    Route::get('/', [
        OrderController::class,
        'index',
    ])->middleware('permission:view-order-details,api');

    Route::post('/', [
        OrderController::class,
        'store',
    ]);

    Route::middleware('permission:view-all-orders,api')->prefix('admin')->group(function () {
        Route::get('/', [
            OrderController::class,
            'adminIndex',
        ]);

        Route::get('/{order}', [
            OrderController::class,
            'adminShow',
        ]);
    });

    Route::put('/{order}/status', [
        OrderController::class,
        'updateStatus',
    ]);

    Route::get('/{order}', [
        OrderController::class,
        'show',
    ])->middleware('permission:view-order-details,api');
});
