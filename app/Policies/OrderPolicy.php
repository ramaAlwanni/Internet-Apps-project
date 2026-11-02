<?php

namespace App\Policies;

use Modules\Order\Models\Order;
use Modules\User\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        return $user->can('view-order-details') && $order->user_id === $user->id;
    }
}
