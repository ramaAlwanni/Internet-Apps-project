<?php

return [
    'permissions' => [
        'view-order-details',
        'change-order-status',
    ],

    'roles' => [
        'admin' => [
            'change-order-status',
            'view-order-details',
        ],

        'user' => [
            'view-order-details'
        ],

    ],
];
