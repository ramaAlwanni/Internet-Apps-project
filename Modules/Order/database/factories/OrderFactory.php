<?php

namespace Modules\Order\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Order\Models\Order;
use Modules\User\Models\User;
use RuntimeException;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $userId = User::query()->inRandomOrder()->value('id');

        if ($userId === null) {
            throw new RuntimeException('Cannot create an order without an existing user.');
        }

        return [
            'user_id' => $userId,
            'total_amount' => $this->faker->randomFloat(2, 1, 10000),
            'status' => $this->faker->randomElement(Order::statuses()),
        ];
    }
}
