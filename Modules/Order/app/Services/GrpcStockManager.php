<?php

namespace Modules\Order\Services;

use App\Contracts\Inventory\InventoryGateway;
use Illuminate\Support\Facades\Log;
use Modules\Order\Contracts\StockManager;
use Throwable;

class GrpcStockManager implements StockManager
{
    public function __construct(
        private readonly InventoryGateway $inventory
    ) {
    }

    public function reserve(string $reservationId, array $items): array
    {
        try {
            $quantities = [];
            foreach ($items as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $quantity = (int) ($item['quantity'] ?? 0);
                if ($productId <= 0 || $quantity <= 0) {
                    return [
                        'success' => false,
                        'message' => 'Product ID and quantity must be greater than zero.',
                    ];
                }

                $quantities[$productId] = ($quantities[$productId] ?? 0) + $quantity;
            }

            if ($quantities === []) {
                return [
                    'success' => false,
                    'message' => 'At least one product is required for a reservation.',
                ];
            }

            foreach ($quantities as $productId => $quantity) {
                $result = $this->inventory->reserveProduct(
                    $reservationId,
                    (int) $productId,
                    $quantity
                );
                if (!$result['success']) {
                    return $this->failure(
                        $reservationId,
                        $result['message'] ?: "Unable to reserve product {$productId}."
                    );
                }
            }

            return [
                'success' => true,
                'message' => 'Stock reserved successfully via gRPC.',
                'reservation_id' => $reservationId,
            ];
        } catch (Throwable $exception) {
            Log::error('Inventory gRPC reservation request failed.', [
                'reservation_id' => $reservationId,
                'exception' => $exception,
            ]);
            return $this->failure($reservationId, 'Inventory gRPC error: ' . $exception->getMessage());
        }
    }

    public function commit(string $reservationId): array
    {
        return $this->inventory->commitReservation($reservationId);
    }

    public function release(string $reservationId): array
    {
        return $this->inventory->releaseReservation($reservationId);
    }

    private function failure(string $reservationId, string $message): array
    {
        try {
            $release = $this->inventory->releaseReservation($reservationId);
            if (!$release['success']) {
                Log::critical('Failed to release a partial inventory reservation.', [
                    'reservation_id' => $reservationId,
                    'error' => $release['message'],
                ]);
                $message .= '; reservation release failed: ' . $release['message'];
            }
        } catch (Throwable $releaseException) {
            Log::critical('Failed to release a partial inventory reservation.', [
                'reservation_id' => $reservationId,
                'exception' => $releaseException,
            ]);
            $message .= '; reservation release failed: ' . $releaseException->getMessage();
        }

        return [
            'success' => false,
            'message' => $message,
            'reservation_id' => $reservationId,
        ];
    }
}
