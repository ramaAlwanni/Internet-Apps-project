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

    /*
    |--------------------------------------------------------------------------
    | Inventory gRPC deadline (seconds)
    |--------------------------------------------------------------------------
    |
    | Every inventory RPC is limited by this deadline. When the warehouse
    | service is down or hangs, the request fails with a clear 503 response
    | instead of waiting forever (or returning a generic 500).
    |
    */

    'grpc_inventory_timeout' => env(
        'GRPC_INVENTORY_TIMEOUT',
        5.0
    ),
];
