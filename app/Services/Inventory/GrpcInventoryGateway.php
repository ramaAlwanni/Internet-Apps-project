<?php

namespace App\Services\Inventory;

use App\Contracts\Inventory\InventoryGateway;
use App\Exceptions\InventoryUnavailableException;
use Grpc\ChannelCredentials;
use Illuminate\Support\Facades\Log;
use Inventory\InventoryServiceClient;
use Inventory\ReserveBatchRequest;
use Inventory\ReserveRequest;
use Inventory\ReserveResponse;
use Inventory\StockItem;
use Inventory\StockRequest;
use Inventory\StockResponse;
use RuntimeException;
use Throwable;

class GrpcInventoryGateway implements InventoryGateway
{
    private const DEFAULT_TIMEOUT_SECONDS = 5.0;

    private const STATUS_DEADLINE_EXCEEDED = 4;

    private const STATUS_UNAVAILABLE = 14;

    public function ensureProductStock(int $productId, int $initialQuantity): void
    {
        $request = new ReserveRequest();
        $request->setProductId($productId);
        $request->setQuantity($initialQuantity);

        $response = $this->call('EnsureProductStock', $request, ReserveResponse::class);
        if (!$response->getSuccess()) {
            throw new RuntimeException(
                $response->getMessage() ?: "Unable to initialize stock for product {$productId}."
            );
        }
    }

    public function getAvailableQuantity(int $productId): int
    {
        $request = new StockRequest();
        $request->setProductId($productId);

        $response = $this->call('GetStockQuantity', $request, StockResponse::class);

        return $response->getAvailableQuantity();
    }

    public function reserveProduct(
        string $reservationId,
        int $productId,
        int $quantity
    ): array {
        $request = $this->reservationRequest($reservationId, $productId, $quantity);
        $response = $this->call('ReserveStock', $request, ReserveResponse::class);

        return [
            'success' => $response->getSuccess(),
            'message' => $response->getMessage(),
        ];
    }

    public function reserveProducts(string $reservationId, array $items): array
    {
        $request = new ReserveBatchRequest();
        $request->setOrderRefId($reservationId);

        foreach ($items as $item) {
            $stockItem = new StockItem();
            $stockItem->setProductId((int) $item['product_id']);
            $stockItem->setQuantity((int) $item['quantity']);
            $request->getItems()[] = $stockItem;
        }

        $response = $this->call('ReserveStockBatch', $request, ReserveResponse::class);

        return [
            'success' => $response->getSuccess(),
            'message' => $response->getMessage(),
            'reservation_id' => $reservationId,
        ];
    }

    public function commitReservation(string $reservationId): array
    {
        $response = $this->call(
            'CommitReservation',
            $this->reservationRequest($reservationId),
            ReserveResponse::class
        );

        return [
            'success' => $response->getSuccess(),
            'message' => $response->getMessage(),
            'reservation_id' => $reservationId,
        ];
    }

    public function releaseReservation(string $reservationId): array
    {
        $response = $this->call(
            'ReleaseReservation',
            $this->reservationRequest($reservationId),
            ReserveResponse::class
        );

        return [
            'success' => $response->getSuccess(),
            'message' => $response->getMessage(),
            'reservation_id' => $reservationId,
        ];
    }

    private function reservationRequest(
        string $reservationId,
        int $productId = 0,
        int $quantity = 0
    ): ReserveRequest {
        $request = new ReserveRequest();
        $request->setOrderRefId($reservationId);
        if ($productId > 0) {
            $request->setProductId($productId);
        }
        if ($quantity > 0) {
            $request->setQuantity($quantity);
        }

        return $request;
    }

    private function call(string $method, object $request, string $responseClass): object
    {
        if (!extension_loaded('grpc')) {
            throw new InventoryUnavailableException(
                'Inventory service is unavailable because the PHP gRPC extension is not installed. Please contact the administrator.',
                new RuntimeException(
                    'The PHP gRPC extension is required to use the inventory service.'
                )
            );
        }

        $host = config('order.grpc_inventory_host', '127.0.0.1:9090');
        $timeoutMicroseconds = $this->timeoutMicroseconds();

        try {
            $client = new InventoryServiceClient($host, [
                'credentials' => ChannelCredentials::createInsecure(),
            ]);
            [$response, $status] = $client->{$method}(
                $request,
                [],
                ['timeout' => $timeoutMicroseconds]
            )->wait();
        } catch (Throwable $exception) {
            Log::error('Inventory gRPC request threw an exception.', [
                'method' => $method,
                'host' => $host,
                'timeout_seconds' => $this->timeoutSeconds(),
                'exception' => $exception,
            ]);

            throw new InventoryUnavailableException(
                InventoryUnavailableException::DEFAULT_MESSAGE,
                $exception
            );
        }

        if ($status->code !== 0) {
            Log::warning('Inventory gRPC request failed.', [
                'method' => $method,
                'host' => $host,
                'status_code' => $status->code,
                'status_details' => $status->details,
            ]);

            throw new InventoryUnavailableException(
                $this->unavailableMessage((int) $status->code),
                new RuntimeException(
                    "Inventory gRPC request failed ({$status->code}): "
                    . ($status->details ?: 'Unknown error')
                )
            );
        }

        if (!$response instanceof $responseClass) {
            Log::error('Inventory gRPC method returned an invalid response.', [
                'method' => $method,
                'expected' => $responseClass,
                'received' => get_debug_type($response),
            ]);

            throw new InventoryUnavailableException(
                InventoryUnavailableException::DEFAULT_MESSAGE
            );
        }

        return $response;
    }

    private function timeoutSeconds(): float
    {
        return max(
            0.5,
            (float) config(
                'order.grpc_inventory_timeout',
                self::DEFAULT_TIMEOUT_SECONDS
            )
        );
    }

    private function timeoutMicroseconds(): int
    {
        return (int) round($this->timeoutSeconds() * 1_000_000);
    }

    private function unavailableMessage(int $statusCode): string
    {
        if ($statusCode === self::STATUS_DEADLINE_EXCEEDED) {
            return sprintf(
                'Inventory service did not respond within %g seconds. Please try again later.',
                $this->timeoutSeconds()
            );
        }

        if ($statusCode === self::STATUS_UNAVAILABLE) {
            return 'Inventory service is currently unavailable. Please try again later.';
        }

        return InventoryUnavailableException::DEFAULT_MESSAGE;
    }
}
