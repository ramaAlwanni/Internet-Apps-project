<?php

namespace Modules\Order\Services;

use Illuminate\Support\Facades\DB;
use Modules\Order\Models\Cart;
use Modules\Order\Models\CartItem;
use Modules\Product\Models\Product;
use RuntimeException;

class CartService
{
    public function getCart(int $userId): Cart
    {
        $cart = Cart::firstOrCreate([
            'user_id' => $userId,
        ]);

        return $cart->load([
            'items.product.category',
        ]);
    }

    public function addItem(
        int $userId,
        int $productId,
        int $quantity
    ): Cart {
        if ($quantity < 1) {
            throw new RuntimeException(
                'Quantity must be greater than zero.'
            );
        }

        $product = Product::findOrFail($productId);

        if ($product->stock < $quantity) {
            throw new RuntimeException(
                "Only {$product->stock} items are available."
            );
        }

        $cart = Cart::firstOrCreate([
            'user_id' => $userId,
        ]);

        $cartItem = CartItem::firstOrNew([
            'cart_id' => $cart->id,
            'product_id' => $productId,
        ]);

        $newQuantity = ($cartItem->exists ? $cartItem->quantity : 0)
            + $quantity;

        if ($newQuantity > $product->stock) {
            throw new RuntimeException(
                "Only {$product->stock} items are available."
            );
        }

        $cartItem->quantity = $newQuantity;
        $cartItem->save();

        return $this->getCart($userId);
    }

    public function updateItem(
        int $userId,
        int $productId,
        int $quantity
    ): Cart {
        if ($quantity < 1) {
            throw new RuntimeException(
                'Quantity must be greater than zero.'
            );
        }

        $product = Product::findOrFail($productId);

        if ($quantity > $product->stock) {
            throw new RuntimeException(
                "Only {$product->stock} items are available."
            );
        }

        $cart = Cart::where('user_id', $userId)->firstOrFail();

        $item = $cart->items()
            ->where('product_id', $productId)
            ->firstOrFail();

        $item->update([
            'quantity' => $quantity,
        ]);

        return $this->getCart($userId);
    }

    public function removeItem(
        int $userId,
        int $productId
    ): Cart {
        $cart = Cart::where('user_id', $userId)->first();

        if (!$cart) {
            return $this->getCart($userId);
        }

        $cart->items()
            ->where('product_id', $productId)
            ->delete();

        return $this->getCart($userId);
    }

    public function clearCart(int $userId): Cart
    {
        $cart = Cart::firstOrCreate([
            'user_id' => $userId,
        ]);

        $cart->items()->delete();

        return $this->getCart($userId);
    }

    public function getCheckoutItems(int $userId): array
    {
        $cart = $this->getCart($userId);

        if ($cart->items->isEmpty()) {
            throw new RuntimeException(
                'Cannot create an order from an empty cart.'
            );
        }

        return $cart->items
            ->map(function (CartItem $item) {
                return [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                ];
            })
            ->values()
            ->all();
    }
}
