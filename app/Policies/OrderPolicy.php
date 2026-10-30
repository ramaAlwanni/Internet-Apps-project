<?php

namespace App\Policies;

use App\Models\User;
use Modules\Order\Models\Order;

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
