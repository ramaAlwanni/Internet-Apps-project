<?php


return [
    /*
    |--------------------------------------------------------------------------
    | Stock Manager
    |--------------------------------------------------------------------------
    |
    | حاليًا نستخدم local.
    | لاحقًا عند إضافة gRPC تصبح القيمة grpc.
    |
    */

    'stock_manager' => env(
        'ORDER_STOCK_MANAGER',
        'local'
    ),

    'grpc_inventory_host' => env(
        'GRPC_INVENTORY_HOST',
        '127.0.0.1:9090'
    ),
];
