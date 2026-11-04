<?php

namespace Modules\Order\Services;

use App\Contracts\Catalog\ProductCatalog;
use App\Contracts\Identity\UserDirectory;
use Illuminate\Support\Facades\DB;
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

            $this->stockManager->commit($reservationId);

            return $order;
        } catch (\Throwable $exception) {
            $this->stockManager->release($reservationId);

            throw $exception;
        }
    }

    public function getUserOrders(int $userId)
    {
        return Order::query()
            ->with('items')
            ->where('user_id', $userId)
            ->latest()
            ->get();
    }

    public function getAllOrders()
    {
        return Order::query()
            ->with('items')
            ->latest()
            ->get()
            ->map(function (Order $order) {
                $order->setAttribute(
                    'customer',
                    $this->userDirectory->findBasicById((int) $order->user_id)
                );

                return $order;
            });
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