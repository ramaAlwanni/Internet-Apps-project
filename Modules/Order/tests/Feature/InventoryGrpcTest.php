<?php

namespace Modules\Order\Tests\Feature;

use Inventory\ReserveRequest;
use Inventory\ReserveResponse;
use Inventory\RollbackRequest;
use Inventory\RollbackResponse;
use Modules\Order\Services\GrpcStockManager;
use Tests\TestCase;

class InventoryGrpcTest extends TestCase
{
    public function test_it_reserves_stock_successfully_when_grpc_server_accepts_request(): void
    {
        $stockManager = $this->createStockManagerForResponses(
            reserveQueue: [
                [new ReserveResponse(['success' => true, 'message' => 'Stock reserved']), (object) ['code' => 0, 'details' => 'OK']],
            ],
            rollbackQueue: [
                [new RollbackResponse(['success' => true, 'message' => 'Stock restored']), (object) ['code' => 0, 'details' => 'OK']],
            ]
        );

        $result = $stockManager->reserve('reservation-success', [
            ['product_id' => 1, 'quantity' => 2],
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('Stock reserved successfully via gRPC', $result['message']);
    }

    public function test_it_returns_failure_when_product_does_not_exist(): void
    {
        $stockManager = $this->createStockManagerForResponses(
            reserveQueue: [
                [new ReserveResponse(['success' => false, 'message' => 'Product not found']), (object) ['code' => 0, 'details' => 'OK']],
            ]
        );

        $result = $stockManager->reserve('reservation-not-found', [
            ['product_id' => 99999, 'quantity' => 1],
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('Product not found', $result['message']);
    }

    public function test_it_returns_failure_when_quantity_is_insufficient(): void
    {
        $stockManager = $this->createStockManagerForResponses(
            reserveQueue: [
                [new ReserveResponse(['success' => false, 'message' => 'Insufficient stock']), (object) ['code' => 0, 'details' => 'OK']],
            ]
        );

        $result = $stockManager->reserve('reservation-out-of-stock', [
            ['product_id' => 99, 'quantity' => 1000],
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('Insufficient stock', $result['message']);
    }

    public function test_it_rolls_back_partial_reservations_when_any_item_fails(): void
    {
        $stockManager = $this->createStockManagerForResponses(
            reserveQueue: [
                [new ReserveResponse(['success' => true, 'message' => 'Stock reserved']), (object) ['code' => 0, 'details' => 'OK']],
                [new ReserveResponse(['success' => false, 'message' => 'Insufficient stock']), (object) ['code' => 0, 'details' => 'OK']],
            ],
            rollbackQueue: [
                [new RollbackResponse(['success' => true, 'message' => 'Stock restored']), (object) ['code' => 0, 'details' => 'OK']],
            ]
        );

        $result = $stockManager->reserve('reservation-partial', [
            ['product_id' => 1, 'quantity' => 2],
            ['product_id' => 2, 'quantity' => 9999],
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('Insufficient stock', $result['message']);
    }

    public function test_it_returns_error_when_grpc_service_is_unavailable(): void
    {
        $stockManager = $this->createStockManagerForResponses(
            reserveQueue: [
                [null, (object) ['code' => 14, 'details' => 'UNAVAILABLE: connection refused']],
            ]
        );

        $result = $stockManager->reserve('reservation-unavailable', [
            ['product_id' => 1, 'quantity' => 1],
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Inventory gRPC request failed', $result['message']);
    }

    public function test_it_sends_product_id_and_quantity_in_rollback_request(): void
    {
        $state = (object) ['captured' => null];

        $stockManager = new class($state) extends GrpcStockManager {
            public function __construct(private object $state)
            {
                parent::__construct();
            }

            protected function getClient()
            {
                return new class($this->state) {
                    public function __construct(private object $state) {}

                    public function ReserveStock(ReserveRequest $request)
                    {
                        $response = $request->getProductId() === 10
                            ? new ReserveResponse(['success' => true, 'message' => 'Stock reserved'])
                            : new ReserveResponse(['success' => false, 'message' => 'Insufficient stock']);

                        return new class($response, (object) ['code' => 0, 'details' => 'OK']) {
                            public function __construct(private mixed $response, private object $status) {}

                            public function wait(): array
                            {
                                return [$this->response, $this->status];
                            }
                        };
                    }

                    public function RollbackStock(RollbackRequest $request)
                    {
                        $this->state->captured = [
                            'product_id' => $request->getProductId(),
                            'quantity' => $request->getQuantity(),
                            'order_ref_id' => $request->getOrderRefId(),
                        ];

                        return new class(new RollbackResponse(['success' => true, 'message' => 'Stock restored']), (object) ['code' => 0, 'details' => 'OK']) {
                            public function __construct(private mixed $response, private object $status) {}

                            public function wait(): array
                            {
                                return [$this->response, $this->status];
                            }
                        };
                    }
                };
            }
        };

        $result = $stockManager->reserve('reservation-rollback-payload', [
            ['product_id' => 10, 'quantity' => 2],
            ['product_id' => 11, 'quantity' => 8],
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame(10, $state->captured['product_id']);
        $this->assertSame(2, $state->captured['quantity']);
        $this->assertSame('reservation-rollback-payload', $state->captured['order_ref_id']);
    }

    public function test_it_releases_stock_when_release_is_called(): void
    {
        $stockManager = $this->createStockManagerForResponses(
            reserveQueue: [
                [new ReserveResponse(['success' => true, 'message' => 'Stock reserved']), (object) ['code' => 0, 'details' => 'OK']],
            ],
            rollbackQueue: [
                [new RollbackResponse(['success' => true, 'message' => 'Stock restored']), (object) ['code' => 0, 'details' => 'OK']],
            ]
        );

        $stockManager->reserve('reservation-release', [
            ['product_id' => 5, 'quantity' => 1],
        ]);

        $result = $stockManager->release('reservation-release');

        $this->assertTrue($result['success']);
        $this->assertSame('Stock released successfully', $result['message']);
    }

    public function test_it_handles_race_condition_by_rejecting_second_reservation_for_same_item(): void
    {
        $stockManager = $this->createStockManagerForResponses(
            reserveQueue: [
                [new ReserveResponse(['success' => true, 'message' => 'Stock reserved']), (object) ['code' => 0, 'details' => 'OK']],
                [new ReserveResponse(['success' => false, 'message' => 'Insufficient stock']), (object) ['code' => 0, 'details' => 'OK']],
            ]
        );

        $first = $stockManager->reserve('reservation-race-1', [['product_id' => 7, 'quantity' => 1]]);
        $second = $stockManager->reserve('reservation-race-2', [['product_id' => 7, 'quantity' => 1]]);

        $this->assertTrue($first['success']);
        $this->assertFalse($second['success']);
        $this->assertSame('Insufficient stock', $second['message']);
    }

    public function test_it_fails_fast_when_grpc_call_exceeds_timeout(): void
    {
        $stockManager = new class extends GrpcStockManager {
            protected function getClient()
            {
                return new class {
                    public function ReserveStock($request)
                    {
                        return new class {
                            public function wait(): array
                            {
                                return [null, (object) ['code' => 4, 'details' => 'Deadline exceeded']];
                            }
                        };
                    }

                    public function RollbackStock($request)
                    {
                        return new class {
                            public function wait(): array
                            {
                                return [null, (object) ['code' => 4, 'details' => 'Deadline exceeded']];
                            }
                        };
                    }
                };
            }
        };

        $result = $stockManager->reserve('reservation-timeout', [
            ['product_id' => 1, 'quantity' => 1],
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Inventory gRPC request failed', $result['message']);
    }

    protected function createStockManagerForResponses(array $reserveQueue = [], array $rollbackQueue = []): GrpcStockManager
    {
        $fakeClient = new class($reserveQueue, $rollbackQueue) {
            private array $reserveQueue;
            private array $rollbackQueue;

            public function __construct(array $reserveQueue, array $rollbackQueue)
            {
                $this->reserveQueue = $reserveQueue;
                $this->rollbackQueue = $rollbackQueue;
            }

            public function ReserveStock(ReserveRequest $request)
            {
                $result = array_shift($this->reserveQueue);
                $result = $result ?? [new ReserveResponse(['success' => true, 'message' => 'Stock reserved']), (object) ['code' => 0, 'details' => 'OK']];

                return new class($result[0] ?? null, $result[1] ?? (object) ['code' => 0, 'details' => 'OK']) {
                    public function __construct(private mixed $response, private object $status) {}

                    public function wait(): array
                    {
                        return [$this->response, $this->status];
                    }
                };
            }

            public function RollbackStock(RollbackRequest $request)
            {
                $result = array_shift($this->rollbackQueue);
                $result = $result ?? [new RollbackResponse(['success' => true, 'message' => 'Stock restored']), (object) ['code' => 0, 'details' => 'OK']];

                return new class($result[0] ?? null, $result[1] ?? (object) ['code' => 0, 'details' => 'OK']) {
                    public function __construct(private mixed $response, private object $status) {}

                    public function wait(): array
                    {
                        return [$this->response, $this->status];
                    }
                };
            }
        };

        return new class($fakeClient) extends GrpcStockManager {
            public function __construct(private object $fakeClient)
            {
                parent::__construct();
            }

            protected function getClient()
            {
                return $this->fakeClient;
            }
        };
    }
}
