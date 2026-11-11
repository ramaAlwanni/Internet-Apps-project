<?php

namespace App\Services\Inventory;

use App\Contracts\Inventory\InventoryGateway;
use Grpc\ChannelCredentials;
use Inventory\InventoryServiceClient;
use Inventory\ReserveBatchRequest;
use Inventory\ReserveRequest;
use Inventory\ReserveResponse;
use Inventory\StockItem;
use Inventory\StockRequest;
use Inventory\StockResponse;
use RuntimeException;

class GrpcInventoryGateway implements InventoryGateway
{
    private const TIMEOUT_MICROSECONDS = 5_000_000;

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
            throw new RuntimeException(
                'The PHP gRPC extension is required to use the inventory service.'
            );
        }

        $host = config('order.grpc_inventory_host', '127.0.0.1:9090');
        $client = new InventoryServiceClient($host, [
            'credentials' => ChannelCredentials::createInsecure(),
        ]);
        [$response, $status] = $client->{$method}(
            $request,
            [],
            ['timeout' => self::TIMEOUT_MICROSECONDS]
        )->wait();

        if ($status->code !== 0) {
            throw new RuntimeException(
                "Inventory gRPC request failed ({$status->code}): "
                . ($status->details ?: 'Unknown error')
            );
        }

        if (!$response instanceof $responseClass) {
            throw new RuntimeException(
                "Inventory gRPC method {$method} returned an invalid response."
            );
        }

        return $response;
    }
}
