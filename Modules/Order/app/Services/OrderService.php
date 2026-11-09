<?php

namespace Modules\Order\Services;

use App\Contracts\Catalog\ProductCatalog;
use App\Contracts\Identity\UserDirectory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Order\Contracts\StockManager;
use Modules\Order\Models\Order;

class OrderService
{
    public function __construct(
        private readonly StockManager $stockManager,
        private readonly ProductCatalog $productCatalog,
        private readonly UserDirectory $userDirectory
    ) {
    }

    public function createOrder(
        int $userId,
        array $items
    ): Order {
        $reservationId = (string) Str::uuid();

        $reservation = $this->stockManager->reserve(
            $reservationId,
            $items
        );

        if (!$reservation['success']) {
            throw new \RuntimeException($reservation['message']);
        }

        try {
            $order = DB::transaction(function () use ($userId, $items) {
                $productIds = collect($items)
                    ->pluck('product_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                $products = $this->productCatalog->findByIds($productIds);

                if (count($products) !== count($productIds)) {
                    throw new \RuntimeException(
                        'One or more products were not found.'
                    );
                }

                $totalAmount = 0;
                $orderItems = [];

                foreach ($items as $item) {
                    $productId = (int) $item['product_id'];
                    $product = $products[$productId] ?? null;
                    $quantity = (int) $item['quantity'];

                    if (!$product) {
                        throw new \RuntimeException(
                            'One or more products were not found.'
                        );
                    }

                    $totalAmount += (float) $product['price'] * $quantity;

                    $orderItems[] = [
                        'product_id' => $productId,
                        'product_name' => $product['name'],
                        'unit_price' => $product['price'],
                        'quantity' => $quantity,
                        'subtotal' => (float) $product['price'] * $quantity,
                    ];
                }

                $order = Order::create([
                    'user_id' => $userId,
                    'total_amount' => $totalAmount,
                    'status' => Order::STATUS_PENDING,
                ]);

                foreach ($orderItems as $item) {
                    $order->items()->create($item);
                }

                return $order->load('items');
            });

        } catch (\Throwable $exception) {
            try {
                $release = $this->stockManager->release($reservationId);
            } catch (\Throwable $releaseException) {
                Log::critical('Failed to release inventory after order creation failed.', [
                    'reservation_id' => $reservationId,
                    'exception' => $releaseException,
                ]);
                throw new \RuntimeException(
                    $exception->getMessage()
                        . '; inventory reservation release failed: '
                        . $releaseException->getMessage(),
                    0,
                    $exception
                );
            }
            if (!$release['success']) {
                Log::critical('Failed to release inventory after order creation failed.', [
                    'reservation_id' => $reservationId,
                    'error' => $release['message'],
                ]);
                throw new \RuntimeException(
                    $exception->getMessage()
                        . '; inventory reservation release failed: '
                        . $release['message'],
                    0,
                    $exception
                );
            }

            throw $exception;
        }

        try {
            $commit = $this->stockManager->commit($reservationId);
            if (!$commit['success']) {
                throw new \RuntimeException($commit['message'] ?: 'Inventory reservation could not be committed.');
            }
        } catch (\Throwable $exception) {
            Log::critical('Order was saved but its inventory reservation could not be committed.', [
                'order_id' => $order->id,
                'reservation_id' => $reservationId,
                'exception' => $exception,
            ]);
            throw new \RuntimeException(
                "Order {$order->id} was saved, but inventory reservation confirmation failed.",
                0,
                $exception
            );
        }

        return $order;
    }

    public function getUserOrders(int $userId)
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $orders = Order::query()
                ->with('items')
                ->where('user_id', $userId)
                ->latest()
                ->get();

            Log::info('User orders query count.', [
                'user_id' => $userId,
                'query_count' => count(DB::getQueryLog()),
            ]);

            return $orders;
        } finally {
            DB::disableQueryLog();
        }
    }

    public function getAllOrders()
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $orders = Order::query()
                ->with(['items', 'user'])
                ->latest()
                ->paginate(15);

            Log::info('All orders query count.', [
                'query_count' => count(DB::getQueryLog()),
            ]);

            return $orders;
        } finally {
            DB::disableQueryLog();
        }
    }

    public function getOrderForUser(
        int $orderId,
        int $userId,
        bool $isAdmin = false
    ): Order {
        $query = Order::query()
            ->with('items')
            ->where('id', $orderId);

        if (!$isAdmin) {
            $query->where('user_id', $userId);
        }

        return $query->firstOrFail();
    }

    public function updateStatus(
        int $orderId,
        string $newStatus
    ): Order {
        return DB::transaction(function () use ($orderId, $newStatus) {
            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($orderId);

            $allowedTransitions = [
                Order::STATUS_PENDING => [
                    Order::STATUS_CONFIRMED,
                    Order::STATUS_CANCELLED,
                ],
                Order::STATUS_CONFIRMED => [
                    Order::STATUS_PROCESSING,
                    Order::STATUS_CANCELLED,
                ],
                Order::STATUS_PROCESSING => [
                    Order::STATUS_SHIPPED,
                ],
                Order::STATUS_SHIPPED => [
                    Order::STATUS_DELIVERED,
                ],
                Order::STATUS_DELIVERED => [],
                Order::STATUS_CANCELLED => [],
            ];

            $allowedStatuses = $allowedTransitions[$order->status] ?? [];

            if (!in_array($newStatus, $allowedStatuses, true)) {
                throw new \RuntimeException(
                    "Cannot change order status from {$order->status} to {$newStatus}."
                );
            }

            $order->update(['status' => $newStatus]);

            return $order->load('items');
        });
    }
}
