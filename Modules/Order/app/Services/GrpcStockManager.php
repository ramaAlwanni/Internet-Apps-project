<?php

namespace Modules\Order\Services;

use App\Contracts\Inventory\InventoryGateway;
use App\Exceptions\InventoryUnavailableException;
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

                $currentQuantity = $quantities[$productId] ?? 0;
                if ($quantity > 2_147_483_647 - $currentQuantity) {
                    return [
                        'success' => false,
                        'message' => 'The combined product quantity exceeds the inventory service limit.',
                    ];
                }

                $quantities[$productId] = $currentQuantity + $quantity;
            }

            if ($quantities === []) {
                return [
                    'success' => false,
                    'message' => 'At least one product is required for a reservation.',
                ];
            }

            ksort($quantities);
            $reservation = $this->inventory->reserveProducts(
                $reservationId,
                array_map(
                    fn (int $productId, int $quantity) => [
                        'product_id' => $productId,
                        'quantity' => $quantity,
                    ],
                    array_keys($quantities),
                    array_values($quantities)
                )
            );

            $reservation['reservation_id'] = $reservationId;

            return $reservation['success']
                ? $reservation
                : $this->classifyFailure($reservation);
        } catch (Throwable $exception) {
            Log::error('Inventory gRPC reservation request failed.', [
                'reservation_id' => $reservationId,
                'exception' => $exception,
            ]);
            return $this->releaseAfterUncertainReservation(
                $reservationId,
                $exception instanceof InventoryUnavailableException
                    ? $exception->getMessage()
                    : 'Inventory gRPC error: ' . $exception->getMessage()
            );
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

    private function releaseAfterUncertainReservation(string $reservationId, string $message): array
    {
        try {
            $release = $this->inventory->releaseReservation($reservationId);
            if (!$release['success']) {
                Log::critical('Failed to release an inventory reservation after an uncertain gRPC result.', [
                    'reservation_id' => $reservationId,
                    'error' => $release['message'],
                ]);
                $message .= '; reservation release failed: ' . $release['message'];
            }
        } catch (Throwable $releaseException) {
            Log::critical('Failed to release an inventory reservation after an uncertain gRPC result.', [
                'reservation_id' => $reservationId,
                'exception' => $releaseException,
            ]);
            $message .= '; reservation release failed: ' . $releaseException->getMessage();
        }

        return [
            'success' => false,
            'message' => $message,
            'reservation_id' => $reservationId,
            'error' => 'inventory_unavailable',
        ];
    }

    /**
     * Give the caller a stable machine-readable reason while keeping the
     * warehouse message (which names the unavailable product) intact.
     */
    private function classifyFailure(array $reservation): array
    {
        $message = (string) ($reservation['message'] ?? 'Stock reservation failed.');
        $normalized = strtolower($message);

        $reservation['error'] = match (true) {
            str_contains($normalized, 'insufficient'),
            str_contains($normalized, 'not enough'),
            str_contains($normalized, 'out of stock') => 'insufficient_stock',
            str_contains($normalized, 'not found') => 'product_not_found',
            default => 'reservation_rejected',
        };

        return $reservation;
    }
}
