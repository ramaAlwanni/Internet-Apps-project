<?php
use Illuminate\Support\Facades\Broadcast;
use Modules\User\Models\User;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

//====================== Admin channel =========================
// Echo subscribes with echo.private('admin.orders') -> "private-admin.orders".
// Laravel strips the "private-" prefix before matching, so register the bare name.
Broadcast::channel('admin.orders', function (User $user) {
    return $user->hasRole('admin') || $user->is_admin === true;
});

//====================== User channel =========================
// The User model has no `orders()` relation, so query the Order model
// directly and make sure the order belongs to the authenticated user.
Broadcast::channel('order.{orderId}', function (User $user, $orderId) {
    return \Modules\Order\Models\Order::query()
        ->whereKey($orderId)
        ->where('user_id', $user->getKey())
        ->exists();
});
