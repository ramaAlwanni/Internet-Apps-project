<?php

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    protected $appends = [
        'subtotal',
    ];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function getSubtotalAttribute(): float
    {
        $product = $this->relationLoaded('product')
            ? $this->getRelation('product')
            : null;
        $price = is_array($product)
            ? ($product['price'] ?? 0)
            : ($product?->price ?? 0);

        return (float) $price * $this->quantity;
    }
}
