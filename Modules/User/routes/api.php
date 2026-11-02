<?php

use Illuminate\Support\Facades\Route;
use Modules\User\Http\Controllers\UserController;


        Route::post('sign-up', [UserController::class, 'signUp'])->name('sign-up');
        Route::post('sign-in', [UserController::class, 'signIn'])->name('sign-in');


