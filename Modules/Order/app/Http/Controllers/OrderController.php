<?php

namespace Modules\Order\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Order\Models\Order;

class OrderController extends Controller
{
    public function show(Order $order)
    {
        $this->authorize('view', $order);

    }
}
