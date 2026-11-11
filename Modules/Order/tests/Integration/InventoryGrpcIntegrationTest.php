<?php

namespace Modules\Order\Tests\Integration;

use App\Contracts\Catalog\ProductCatalog;
use App\Contracts\Identity\UserDirectory;
use App\Services\Inventory\GrpcInventoryGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Order\Services\GrpcStockManager;
use Modules\Order\Services\OrderService;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class InventoryGrpcIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('RUN_INVENTORY_INTEGRATION_TESTS') !== '1') {
            $this->markTestSkipped(
                'Set RUN_INVENTORY_INTEGRATION_TESTS=1 to run against the live inventory service.'
            );
        }

        if (!extension_loaded('grpc')) {
            $this->fail('The PHP gRPC extension is required for inventory integration tests.');
        }
    }

    public function test_it_reads_reserves_and_releases_stock_through_live_grpc(): void
    {
        $inventory = new GrpcInventoryGateway();
        $productId = $this->newTestProductId();
        $reservationId = (string) Str::uuid();

        $inventory->ensureProductStock($productId, 1);
        $this->assertSame(1, $inventory->getAvailableQuantity($productId));

        $reservation = $inventory->reserveProduct($reservationId, $productId, 1);

        $this->assertTrue($reservation['success'], $reservation['message']);
        $this->assertSame(0, $inventory->getAvailableQuantity($productId));

        $release = $inventory->releaseReservation($reservationId);

        $this->assertTrue($release['success'], $release['message']);
        $this->assertSame(1, $inventory->getAvailableQuantity($productId));
    }

    public function test_batch_reservation_is_atomic_when_one_product_has_insufficient_stock(): void
    {
        $inventory = new GrpcInventoryGateway();
        $firstProductId = $this->newTestProductId();
        $secondProductId = $this->newTestProductId();
        $reservationId = (string) Str::uuid();
        $inventory->ensureProductStock($firstProductId, 1);
        $inventory->ensureProductStock($secondProductId, 1);

        $failedReservation = $inventory->reserveProducts($reservationId, [
            ['product_id' => $firstProductId, 'quantity' => 1],
            ['product_id' => $secondProductId, 'quantity' => 2],
        ]);

        $this->assertFalse($failedReservation['success']);
        $this->assertStringContainsString('Insufficient stock', $failedReservation['message']);
        $this->assertSame(1, $inventory->getAvailableQuantity($firstProductId));
        $this->assertSame(1, $inventory->getAvailableQuantity($secondProductId));

        $successfulReservation = $inventory->reserveProducts($reservationId, [
            ['product_id' => $firstProductId, 'quantity' => 1],
            ['product_id' => $secondProductId, 'quantity' => 1],
        ]);

        $this->assertTrue($successfulReservation['success'], $successfulReservation['message']);
        $this->assertSame(0, $inventory->getAvailableQuantity($firstProductId));
        $this->assertSame(0, $inventory->getAvailableQuantity($secondProductId));

        $release = $inventory->releaseReservation($reservationId);

        $this->assertTrue($release['success'], $release['message']);
        $this->assertSame(1, $inventory->getAvailableQuantity($firstProductId));
        $this->assertSame(1, $inventory->getAvailableQuantity($secondProductId));
    }

    public function test_only_one_live_grpc_reservation_wins_a_race_for_the_last_item(): void
    {
        $inventory = new GrpcInventoryGateway();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $productId = $this->newTestProductId();
            $inventory->ensureProductStock($productId, 1);

            $reservationIds = [(string) Str::uuid(), (string) Str::uuid()];
            $workers = array_map(
                fn (string $reservationId) => new Process([
                    PHP_BINARY,
                    __DIR__ . '/Support/reserve-worker.php',
                    (string) $productId,
                    $reservationId,
                    config('order.grpc_inventory_host'),
                ], base_path(), timeout: 15),
                $reservationIds
            );

            foreach ($workers as $worker) {
                $worker->start();
            }

            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertSame(0, $worker->getExitCode(), $worker->getErrorOutput());
                $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            $successfulReservations = array_values(array_filter(
                $results,
                fn (array $result) => $result['success'] === true
            ));

            $this->assertCount(
                1,
                $successfulReservations,
                "Exactly one concurrent reservation must succeed on attempt {$attempt}."
            );
            $failedReservations = array_values(array_filter(
                $results,
                fn (array $result) => $result['success'] === false
            ));
            $this->assertCount(1, $failedReservations);
            $this->assertStringContainsString('Insufficient stock', $failedReservations[0]['message']);
            $this->assertSame(0, $inventory->getAvailableQuantity($productId));

            $release = $inventory->releaseReservation(
                $successfulReservations[0]['reservation_id']
            );

            $this->assertTrue($release['success'], $release['message']);
            $this->assertSame(1, $inventory->getAvailableQuantity($productId));
        }
    }

    public function test_failed_order_persistence_releases_the_live_inventory_reservation(): void
    {
        $inventory = new GrpcInventoryGateway();
        $productId = $this->newTestProductId();
        $inventory->ensureProductStock($productId, 1);

        $catalog = \Mockery::mock(ProductCatalog::class);
        $catalog->shouldReceive('findByIds')
            ->once()
            ->with([$productId])
            ->andReturn([
                $productId => [
                    'id' => $productId,
                    'name' => 'gRPC integration test product',
                    'price' => 1.00,
                ],
            ]);

        $service = new OrderService(
            new GrpcStockManager($inventory),
            $catalog,
            \Mockery::mock(UserDirectory::class)
        );

        try {
            $service->createOrder(PHP_INT_MAX, [
                ['product_id' => $productId, 'quantity' => 1],
            ]);
            $this->fail('Order persistence should fail for a nonexistent user.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('SQLSTATE', $exception->getMessage());
        }

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(1, $inventory->getAvailableQuantity($productId));
    }

    public function test_unavailable_grpc_server_fails_within_the_configured_deadline(): void
    {
        config(['order.grpc_inventory_host' => '127.0.0.1:1']);
        $startedAt = microtime(true);

        try {
            (new GrpcInventoryGateway())->getAvailableQuantity($this->newTestProductId());
            $this->fail('The unavailable gRPC server should cause the request to fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Inventory gRPC request failed', $exception->getMessage());
        }

        $this->assertLessThan(5.5, microtime(true) - $startedAt);
    }

    public function test_slow_grpc_server_is_stopped_by_the_five_second_deadline(): void
    {
        $server = new Process(
            [PHP_BINARY, __DIR__ . '/Support/grpc-blackhole.php'],
            base_path(),
            timeout: 20
        );
        $server->start();

        try {
            $port = null;
            $server->waitUntil(function (string $type, string $output) use (&$port): bool {
                if ($type === Process::OUT && preg_match('/PORT:(\d+)/', $output, $matches)) {
                    $port = (int) $matches[1];
                    return true;
                }

                return false;
            });

            $this->assertNotNull($port, $server->getErrorOutput());
            config(['order.grpc_inventory_host' => "127.0.0.1:{$port}"]);
            $startedAt = microtime(true);

            try {
                (new GrpcInventoryGateway())->getAvailableQuantity($this->newTestProductId());
                $this->fail('The nonresponsive gRPC server should exceed the request deadline.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('request failed (4)', strtolower($exception->getMessage()));
                $this->assertStringContainsString('deadline', strtolower($exception->getMessage()));
            }

            $elapsed = microtime(true) - $startedAt;
            $this->assertGreaterThanOrEqual(4.0, $elapsed);
            $this->assertLessThan(6.5, $elapsed);
        } finally {
            $server->stop(0.1);
        }
    }

    public function test_committed_reservations_cannot_be_released(): void
    {
        $inventory = new GrpcInventoryGateway();
        $productId = $this->newTestProductId();
        $reservationId = (string) Str::uuid();
        $inventory->ensureProductStock($productId, 1);

        $reservation = $inventory->reserveProduct($reservationId, $productId, 1);
        $this->assertTrue($reservation['success'], $reservation['message']);

        $commit = $inventory->commitReservation($reservationId);
        $this->assertTrue($commit['success'], $commit['message']);

        $release = $inventory->releaseReservation($reservationId);

        $this->assertFalse($release['success']);
        $this->assertSame(0, $inventory->getAvailableQuantity($productId));
    }

    private function newTestProductId(): int
    {
        return random_int(1_000_000_000_000_000, 9_000_000_000_000_000);
    }
}
