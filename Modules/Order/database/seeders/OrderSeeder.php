<?php

namespace Modules\Order\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Order\Models\Order;
use Modules\Product\Models\Product;
use Modules\User\Models\User;
use RuntimeException;

class OrderSeeder extends Seeder
{
    private const ORDER_COUNT = 100_000;

    private const CHUNK_SIZE = 500;

    private const MIN_ITEMS_PER_ORDER = 1;

    private const MAX_ITEMS_PER_ORDER = 5;

    public function run(): void
    {
        $userIds = User::query()->pluck('id')->all();
        $products = Product::query()
            ->get(['id', 'name', 'price'])
            ->map(fn (Product $product): array => [
                'id' => (int) $product->id,
                'name' => $product->name,
                'price_cents' => (int) round((float) $product->price * 100),
            ])
            ->all();

        if ($userIds === []) {
            throw new RuntimeException('Seed users before generating orders.');
        }

        if ($products === []) {
            throw new RuntimeException('Seed products before generating orders.');
        }

        $faker = fake();
        $seeded = 0;

        while ($seeded < self::ORDER_COUNT) {
            $batchSize = min(self::CHUNK_SIZE, self::ORDER_COUNT - $seeded);

            DB::transaction(function () use ($batchSize, $userIds, $products, $faker): void {
                $orderItems = [];

                for ($i = 0; $i < $batchSize; $i++) {
                    $itemCount = $faker->numberBetween(
                        self::MIN_ITEMS_PER_ORDER,
                        min(self::MAX_ITEMS_PER_ORDER, count($products))
                    );
                    $productIndexes = array_rand($products, $itemCount);
                    $productIndexes = is_array($productIndexes)
                        ? $productIndexes
                        : [$productIndexes];

                    $createdAt = now()->subDays($faker->numberBetween(0, 365));
                    $totalCents = 0;
                    $itemsForOrder = [];

                    foreach ($productIndexes as $productIndex) {
                        $product = $products[$productIndex];
                        $quantity = $faker->numberBetween(1, 5);
                        $subtotalCents = $product['price_cents'] * $quantity;
                        $totalCents += $subtotalCents;

                        $itemsForOrder[] = [
                            'product_id' => $product['id'],
                            'product_name' => $product['name'],
                            'unit_price' => $this->formatCents($product['price_cents']),
                            'quantity' => $quantity,
                            'subtotal' => $this->formatCents($subtotalCents),
                            'created_at' => $createdAt,
                            'updated_at' => $createdAt,
                        ];
                    }

                    $orderId = DB::table('orders')->insertGetId([
                        'user_id' => $userIds[array_rand($userIds)],
                        'total_amount' => $this->formatCents($totalCents),
                        'status' => $faker->randomElement(Order::statuses()),
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);

                    foreach ($itemsForOrder as $item) {
                        $item['order_id'] = $orderId;
                        $orderItems[] = $item;
                    }
                }

                DB::table('order_items')->insert($orderItems);
            });

            $seeded += $batchSize;
            $this->command?->getOutput()->writeln("Seeded {$seeded}/".self::ORDER_COUNT.' orders.');
        }
    }

    private function formatCents(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
