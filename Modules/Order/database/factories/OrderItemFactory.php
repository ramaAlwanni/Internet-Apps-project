<?php

namespace Modules\Order\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Product\Models\Product;
use RuntimeException;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $orderId = Order::query()->inRandomOrder()->value('id');

        if ($orderId === null) {
            throw new RuntimeException('Cannot create an order item without an existing order.');
        }

        $product = Product::query()
            ->inRandomOrder()
            ->first(['id', 'name', 'price']);

        if ($product === null) {
            throw new RuntimeException('Cannot create an order item without an existing product.');
        }

        $quantity = $this->faker->numberBetween(1, 5);
        $unitPrice = (float) $product->price;

        return [
            'order_id' => $orderId,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'subtotal' => round($unitPrice * $quantity, 2),
        ];
    }
}
