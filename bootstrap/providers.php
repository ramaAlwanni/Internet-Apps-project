<?php

use App\Providers\AppServiceProvider;
use Modules\Order\Providers\OrderServiceProvider;
use Modules\Product\Providers\ProductServiceProvider;
use Modules\User\Providers\UserServiceProvider;

return [
    AppServiceProvider::class,

    OrderServiceProvider::class,
    ProductServiceProvider::class,
    UserServiceProvider::class,
];
