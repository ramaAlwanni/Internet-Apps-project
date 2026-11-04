<?php

return [
    'permissions' => [
        'view-order-details',
        'view-all-orders',
        'change-order-status',
    ],

    'roles' => [
        'admin' => [
            'change-order-status',
            'view-order-details',
            'view-all-orders',
        ],

        'user' => [
            'view-order-details',
        ],

    ],
];
