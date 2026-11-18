<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| Broadcasting authentication
|--------------------------------------------------------------------------
|
| Laravel's default /broadcasting/auth route is registered with the "web"
| (session) middleware. This SPA authenticates with a bearer token, so expose
| an API-guarded endpoint and point Echo's authEndpoint at
| /api/broadcasting/auth.
|
*/
Route::post('/broadcasting/auth', function (Request $request) {
    return Broadcast::auth($request);
})->middleware('auth:api');


