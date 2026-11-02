<?php

use Illuminate\Support\Facades\Route;
use Modules\User\Http\Controllers\UserController;

Route::prefix('v1')->name('v1.')->group(function () {
    Route::prefix('user')->name('user.')->group(function () {
        Route::post('sign-up', [UserController::class, 'signUp'])->name('sign-up');
        Route::post('sign-in', [UserController::class, 'signIn'])->name('sign-in');
    });
});
