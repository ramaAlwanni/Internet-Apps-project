<?php

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\User\Models\User;

class Cart extends Model
{
    protected $fillable = [
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }
}
