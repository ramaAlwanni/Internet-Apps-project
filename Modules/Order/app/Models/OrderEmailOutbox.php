<?php

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Model;

class OrderEmailOutbox extends Model
{
    protected $table = 'order_email_outbox';

    protected $fillable = [
        'order_id',
        'payload',
        'status',
        'attempts',
        'last_error',
        'available_at',
        'published_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'available_at' => 'datetime',
        'published_at' => 'datetime',
    ];
}
