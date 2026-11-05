<?php

namespace Modules\Order\app\Services;

use Inventory\ReserveRequest;
use Inventory\RollbackRequest;
use Inventory\InventoryServiceClient;
use Modules\Order\Contracts\StockManager;

class GrpcStockManager implements StockManager
{
    protected string $serverAddr;

    /**
     * @var array<string, array<int, array{product_id:int, quantity:int}>>
     */
    protected array $reservationItems = [];

    public function __construct()
    {
        $this->serverAddr = env('GRPC_INVENTORY_HOST', 'localhost:9090');
    }

    protected function getClient()
    {
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
        $call = $client->{$method}($request, [
            'timeout' => $timeoutMicroseconds,
        ]);

        return $call->wait();
    }

    public function reserve(string $reservationId, array $items): array
    {
        $client = $this->getClient();
        $reservedItems = [];

        try {
            foreach ($items as $item) {
                $request = new ReserveRequest();
                $request->setProductId((int) $item['product_id']);
                $request->setQuantity((int) $item['quantity']);
                $request->setOrderRefId($reservationId);

                list($response, $status) = $this->callWithTimeout($client, 'ReserveStock', $request);

                if ($status->code !== 0) {
                    $this->rollbackPartial($reservationId, $reservedItems);
                    return [
                        'success' => false,
                        'message' => 'فشل الاتصال بخدمة المستودع: ' . ($status->details ?? 'Unknown error'),
                    ];
                }

                if ($response instanceof \Inventory\ReserveResponse && !$response->getSuccess()) {
                    $this->rollbackPartial($reservationId, $reservedItems);
                    return [
                        'success' => false,
                        'message' => $response->getMessage() ?: 'الكمية غير متوفرة للمنتج: ' . $item['product_id'],
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
            $this->rollbackPartial($reservationId, $reservedItems);
            return [
                'success' => false,
                'message' => 'خطأ استثنائي في اتصال gRPC: ' . $e->getMessage(),
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
                        'message' => 'فشل الـ Rollback: ' . ($status->details ?? 'Unknown error'),
                    ];
                }

                if ($response instanceof \Inventory\RollbackResponse && !$response->getSuccess()) {
                    unset($this->reservationItems[$reservationId]);

                    return [
                        'success' => false,
                        'message' => $response->getMessage() ?: 'فشل إعادة الكمية للمنتج: ' . $item['product_id'],
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
                'message' => 'خطأ في الـ Rollback: ' . $e->getMessage(),
            ];
        }
    }
}
