<?php

use Illuminate\Support\Facades\Route;
use Modules\Product\Http\Controllers\CategoryController;
use Modules\Product\Http\Controllers\ProductController;

// Route::get('/', [ProductController::class, 'index'])->middleware(['etag']);
// Route::delete('/{id}', [ProductController::class, 'destroy']);


Route::middleware('auth:api')->group(function () {


    Route::get('/', [ProductController::class, 'index'])->middleware(['etag']);
    // Route::get('/', [ProductController::class, 'index']);
    Route::delete('/{id}', [ProductController::class, 'destroy']);


    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{category}', [CategoryController::class, 'show']);

    
    Route::middleware('role:admin')->group(function () {
        Route::post('/', [ProductController::class, 'store']);

        Route::post('categories', [CategoryController::class, 'store']);
        Route::put('categories/{category}', [CategoryController::class, 'update']);
        Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
    });
});
