<?php

use Illuminate\Support\Facades\Route;
use Modules\Product\Http\Controllers\ProductController;




Route::post('/', [ProductController::class, 'store']);
Route::get('/', [ProductController::class, 'index']);

