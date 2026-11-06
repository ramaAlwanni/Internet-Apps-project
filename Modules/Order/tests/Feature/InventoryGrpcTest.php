<?php

namespace Modules\Order\Tests\Feature;

use App\Contracts\Inventory\InventoryGateway;
use Modules\Order\Services\GrpcStockManager;
use RuntimeException;
use Tests\TestCase;

class InventoryGrpcTest extends TestCase
{
    public function test_it_reserves_stock_successfully_when_grpc_server_accepts_request(): void
    {
        $gateway = \Mockery::mock(InventoryGateway::class);
        $gateway->shouldReceive('reserveProduct')
            ->once()
            ->with('reservation-success', 1, 2)
            ->andReturn(['success' => true, 'message' => 'Reserved']);

        $result = (new GrpcStockManager($gateway))->reserve(
            'reservation-success',
            [['product_id' => 1, 'quantity' => 2]]
        );

        $this->assertTrue($result['success']);
        $this->assertSame('reservation-success', $result['reservation_id']);
    }

    public function test_it_returns_failure_when_product_does_not_exist(): void
    {
        $gateway = \Mockery::mock(InventoryGateway::class);
        $gateway->shouldReceive('reserveProduct')
            ->once()
            ->andReturn(['success' => false, 'message' => 'Product not found']);
        $gateway->shouldReceive('releaseReservation')
            ->once()
            ->andReturn(['success' => true, 'message' => 'Released']);

        $result = (new GrpcStockManager($gateway))->reserve(
            'reservation-not-found',
            [['product_id' => 99999, 'quantity' => 1]]
        );

        $this->assertFalse($result['success']);
        $this->assertSame('Product not found', $result['message']);
    }

    public function test_it_returns_failure_when_quantity_is_insufficient(): void
    {
        $gateway = \Mockery::mock(InventoryGateway::class);
        $gateway->shouldReceive('reserveProduct')
            ->once()
            ->andReturn(['success' => false, 'message' => 'Insufficient stock']);
        $gateway->shouldReceive('releaseReservation')
            ->once()
            ->andReturn(['success' => true, 'message' => 'Released']);

        $result = (new GrpcStockManager($gateway))->reserve(
            'reservation-insufficient',
            [['product_id' => 99, 'quantity' => 1000]]
        );

        $this->assertFalse($result['success']);
        $this->assertSame('Insufficient stock', $result['message']);
    }

    public function test_it_releases_partial_reservations_when_any_item_fails(): void
    {
        $gateway = \Mockery::mock(InventoryGateway::class);
        $gateway->shouldReceive('reserveProduct')
            ->ordered()
            ->with('reservation-partial', 1, 2)
            ->andReturn(['success' => true, 'message' => 'Reserved']);
        $gateway->shouldReceive('reserveProduct')
            ->ordered()
            ->with('reservation-partial', 2, 9999)
            ->andReturn(['success' => false, 'message' => 'Insufficient stock']);
        $gateway->shouldReceive('releaseReservation')
            ->once()
            ->with('reservation-partial')
            ->andReturn(['success' => true, 'message' => 'Released']);

        $result = (new GrpcStockManager($gateway))->reserve(
            'reservation-partial',
            [
                ['product_id' => 1, 'quantity' => 2],
                ['product_id' => 2, 'quantity' => 9999],
            ]
        );

        $this->assertFalse($result['success']);
        $this->assertSame('Insufficient stock', $result['message']);
    }

    public function test_it_releases_persisted_reservation_without_in_memory_item_state(): void
    {
        $gateway = \Mockery::mock(InventoryGateway::class);
        $gateway->shouldReceive('releaseReservation')
            ->once()
            ->with('reservation-release')
            ->andReturn(['success' => true, 'message' => 'Released']);

        $result = (new GrpcStockManager($gateway))->release('reservation-release');

        $this->assertTrue($result['success']);
        $this->assertSame('Released', $result['message']);
    }

    public function test_it_returns_the_first_result_and_rejects_the_competing_reservation(): void
    {
        $gateway = \Mockery::mock(InventoryGateway::class);
        $gateway->shouldReceive('reserveProduct')
            ->ordered()
            ->once()
            ->with('reservation-race-1', 7, 1)
            ->andReturn(['success' => true, 'message' => 'Reserved']);
        $gateway->shouldReceive('reserveProduct')
            ->ordered()
            ->once()
            ->with('reservation-race-2', 7, 1)
            ->andReturn(['success' => false, 'message' => 'Insufficient stock']);
        $gateway->shouldReceive('releaseReservation')
            ->once()
            ->andReturn(['success' => true, 'message' => 'Released']);

        $manager = new GrpcStockManager($gateway);
        $first = $manager->reserve('reservation-race-1', [['product_id' => 7, 'quantity' => 1]]);
        $second = $manager->reserve('reservation-race-2', [['product_id' => 7, 'quantity' => 1]]);

        $this->assertTrue($first['success']);
        $this->assertFalse($second['success']);
        $this->assertSame('Insufficient stock', $second['message']);
    }

    public function test_it_returns_an_error_when_the_grpc_call_exceeds_its_deadline(): void
    {
        $gateway = \Mockery::mock(InventoryGateway::class);
        $gateway->shouldReceive('reserveProduct')
            ->once()
            ->andThrow(new RuntimeException('Inventory gRPC request failed (4): DEADLINE_EXCEEDED'));
        $gateway->shouldReceive('releaseReservation')
            ->once()
            ->andReturn(['success' => true, 'message' => 'Released']);

        $result = (new GrpcStockManager($gateway))->reserve(
            'reservation-timeout',
            [['product_id' => 1, 'quantity' => 1]]
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('DEADLINE_EXCEEDED', $result['message']);
    }

    public function test_it_commits_a_persisted_reservation_through_the_gateway(): void
    {
        $gateway = \Mockery::mock(InventoryGateway::class);
        $gateway->shouldReceive('commitReservation')
            ->once()
            ->with('reservation-commit')
            ->andReturn(['success' => true, 'message' => 'Committed']);

        $result = (new GrpcStockManager($gateway))->commit('reservation-commit');

        $this->assertTrue($result['success']);
        $this->assertSame('Committed', $result['message']);
    }

    public function test_it_aggregates_duplicate_product_lines_before_reserving(): void
    {
        $gateway = \Mockery::mock(InventoryGateway::class);
        $gateway->shouldReceive('reserveProduct')
            ->once()
            ->with('reservation-duplicates', 5, 5)
            ->andReturn(['success' => true, 'message' => 'Reserved']);

        $result = (new GrpcStockManager($gateway))->reserve(
            'reservation-duplicates',
            [
                ['product_id' => 5, 'quantity' => 2],
                ['product_id' => 5, 'quantity' => 3],
            ]
        );

        $this->assertTrue($result['success']);
    }
}
