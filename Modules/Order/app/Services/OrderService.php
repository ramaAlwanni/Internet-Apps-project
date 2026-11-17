<?php

namespace Modules\Order\Services;

use App\Contracts\Catalog\ProductCatalog;
use App\Contracts\Identity\UserDirectory;
use App\Exceptions\InventoryUnavailableException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Order\Contracts\StockManager;
use Modules\Order\Exceptions\InsufficientStockException;
use Modules\Order\Models\Order;
use Modules\Order\Events\OrderCreatedEvent;
use Modules\Order\Events\OrderStatusUpdatedEvent;

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

        try {
            $reservation = $this->stockManager->reserve(
                $reservationId,
                $items
            );
        } catch (InsufficientStockException | InventoryUnavailableException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'insufficient stock')) {
                throw new InsufficientStockException(
                    $this->insufficientStockMessage($exception->getMessage()),
                    $exception
                );
            }

            throw $exception;
        }

        if (!$reservation['success']) {
            $message = (string) (
                $reservation['message']
                    ?? 'Stock reservation failed.'
            );
            $error = $reservation['error'] ?? null;

            if ($error === 'inventory_unavailable') {
                throw new InventoryUnavailableException($message);
            }

            if (
                $error === 'insufficient_stock'
                || str_contains(strtolower($message), 'insufficient stock')
            ) {
                throw new InsufficientStockException(
                    $this->insufficientStockMessage($message)
                );
            }

            throw new \RuntimeException($message);
        }

        try {
            $order = DB::transaction(function () use ($userId, $items) {
                $productIds = collect($items)
                    ->pluck('product_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                // Stock is already reserved at this point. Read catalog data
                // locally so a warehouse failure cannot fail the order save
                // (and force a reservation release) after a successful reserve.
                $products = $this->productCatalog->findSnapshotsByIds($productIds);

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

        // Broadcast only once the order and its stock reservation are safely
        // committed, so the admin table never shows an order that was rolled back.
        event(new OrderCreatedEvent($order));

        return $order;
    }

    private function insufficientStockMessage(string $warehouseMessage): string
    {
        $warehouseMessage = trim($warehouseMessage);

        if ($warehouseMessage === '') {
            return InsufficientStockException::DEFAULT_MESSAGE;
        }

        if (str_contains(strtolower($warehouseMessage), 'insufficient stock')) {
            return $warehouseMessage
                . ' Please reduce the quantity or choose another product.';
        }

        return $warehouseMessage;
    }

    public function getUserOrders(int $userId, int $perPage = 10)
    {
        return Order::query()
            ->with('items')
            ->where('user_id', $userId)
            ->latest()
            ->paginate($perPage);
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
        $changed = false;

        $order = DB::transaction(function () use ($orderId, $newStatus, &$changed) {
            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($orderId);

            if (!in_array($newStatus, Order::statuses(), true)) {
                throw new \RuntimeException(
                    "Unknown order status: {$newStatus}."
                );
            }

            // Delivered and cancelled orders are final: they can no longer be
            // edited, otherwise a completed sale could silently be reopened.
            $finalStatuses = [
                Order::STATUS_DELIVERED,
                Order::STATUS_CANCELLED,
            ];

            if (
                in_array($order->status, $finalStatuses, true)
                && $newStatus !== $order->status
            ) {
                throw new \RuntimeException(
                    "Cannot change order status from {$order->status} to {$newStatus}."
                );
            }

            // Selecting the current status is a valid no-op (no notification).
            if ($newStatus === $order->status) {
                return $order->load('items');
            }

            $order->update(['status' => $newStatus]);
            $changed = true;

            return $order->load('items');
        });

        // Broadcast only after the transaction commits, so clients never
        // receive a status change that was later rolled back.
        if ($changed) {
            event(new OrderStatusUpdatedEvent($order));
        }

        return $order;
    }
}
