<?php

namespace Modules\Order\Services;

use Inventory\ReserveRequest;
use Inventory\RollbackRequest;
use Inventory\InventoryServiceClient;
use Modules\Order\Contracts\StockManager;
use RuntimeException;

class GrpcStockManager implements StockManager
{
    protected string $serverAddr;

    /**
     * @var array<string, array<int, array{product_id:int, quantity:int}>>
     */
    protected array $reservationItems = [];

    public function __construct()
    {
        $this->serverAddr = config(
            'order.grpc_inventory_host',
            '127.0.0.1:9090'
        );
    }

    protected function getClient()
    {
        if (!extension_loaded('grpc')) {
            throw new RuntimeException(
                'The PHP gRPC extension is required to use the gRPC stock manager.'
            );
        }

        $credentials = \Grpc\ChannelCredentials::createInsecure();

        return new InventoryServiceClient($this->serverAddr, [
            'credentials' => $credentials,
        ]);
    }

    /**
     * Executes a gRPC call with a timeout to avoid hanging forever.
     */
    protected function callWithTimeout($client, string $method, $request, int $timeoutMicroseconds = 5000000): array
    {
        $call = $client->{$method}(
            $request,
            [],
            ['timeout' => $timeoutMicroseconds]
        );

        return $call->wait();
    }

    public function reserve(string $reservationId, array $items): array
    {
        $reservedItems = [];

        try {
            $client = $this->getClient();

            foreach ($items as $item) {
                $request = new ReserveRequest();
                $request->setProductId((int) $item['product_id']);
                $request->setQuantity((int) $item['quantity']);
                $request->setOrderRefId($reservationId);

                list($response, $status) = $this->callWithTimeout($client, 'ReserveStock', $request);

                if ($status->code !== 0) {
                    $rollback = $this->rollbackPartial($reservationId, $reservedItems);

                    return [
                        'success' => false,
                        'message' => 'Inventory gRPC request failed: '
                            . ($status->details ?? 'Unknown error')
                            . (!$rollback['success'] ? '; rollback failed: ' . $rollback['message'] : ''),
                    ];
                }

                if (!$response instanceof \Inventory\ReserveResponse) {
                    throw new RuntimeException('Inventory gRPC returned an invalid reserve response.');
                }

                if (!$response->getSuccess()) {
                    $rollback = $this->rollbackPartial($reservationId, $reservedItems);

                    return [
                        'success' => false,
                        'message' => ($response->getMessage() ?: 'Insufficient stock for product: ' . $item['product_id'])
                            . (!$rollback['success'] ? '; rollback failed: ' . $rollback['message'] : ''),
                    ];
                }

                $reservedItems[] = [
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (int) $item['quantity'],
                ];
            }

            $this->reservationItems[$reservationId] = $reservedItems;

            return [
                'success' => true,
                'message' => 'Stock reserved successfully via gRPC',
            ];
        } catch (\Throwable $e) {
            $rollback = $this->rollbackPartial($reservationId, $reservedItems);

            return [
                'success' => false,
                'message' => 'Inventory gRPC error: ' . $e->getMessage()
                    . (!$rollback['success'] ? '; rollback failed: ' . $rollback['message'] : ''),
            ];
        }
    }

    public function commit(string $reservationId): array
    {
        unset($this->reservationItems[$reservationId]);

        return [
            'success' => true,
            'message' => 'Stock reservation committed',
        ];
    }

    public function release(string $reservationId): array
    {
        $items = $this->reservationItems[$reservationId] ?? [];

        return $this->rollbackPartial($reservationId, $items);
    }

    private function rollbackPartial(string $reservationId, array $items = []): array
    {
        try {
            if (empty($items)) {
                unset($this->reservationItems[$reservationId]);

                return [
                    'success' => true,
                    'message' => 'No stock reservation items to rollback',
                ];
            }

            $client = $this->getClient();

            foreach ($items as $item) {
                $request = new RollbackRequest();
                $request->setProductId((int) $item['product_id']);
                $request->setQuantity((int) $item['quantity']);
                $request->setOrderRefId($reservationId);

                list($response, $status) = $this->callWithTimeout($client, 'RollbackStock', $request);

                if ($status->code !== 0) {
                    if (function_exists('logger')) {
                        logger()->error("Failed to rollback stock for order: {$reservationId}");
                    }

                    unset($this->reservationItems[$reservationId]);

                    return [
                        'success' => false,
                        'message' => 'Rollback request failed: ' . ($status->details ?? 'Unknown error'),
                    ];
                }

                if (!$response instanceof \Inventory\RollbackResponse) {
                    throw new RuntimeException('Inventory gRPC returned an invalid rollback response.');
                }

                if (!$response->getSuccess()) {
                    unset($this->reservationItems[$reservationId]);

                    return [
                        'success' => false,
                        'message' => $response->getMessage() ?: 'Failed to restore stock for product: ' . $item['product_id'],
                    ];
                }
            }

            unset($this->reservationItems[$reservationId]);

            return [
                'success' => true,
                'message' => 'Stock released successfully',
            ];
        } catch (\Throwable $e) {
            unset($this->reservationItems[$reservationId]);

            return [
                'success' => false,
                'message' => 'Rollback error: ' . $e->getMessage(),
            ];
        }
    }
}
