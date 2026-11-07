<?php

namespace Inventory;

use Grpc\BaseStub;

class InventoryServiceClient extends BaseStub
{
    public function __construct($hostname, $opts, $channel = null)
    {
        parent::__construct($hostname, $opts, $channel);
    }

    /**
     * @param \Inventory\StockRequest $argument
     * @param array $metadata
     * @param array $options
     * @return \Grpc\UnaryCall
     */
    public function GetStockQuantity(\Inventory\StockRequest $argument, $metadata = [], $options = [])
    {
        return $this->_simpleRequest(
            '/inventory.InventoryService/GetStockQuantity',
            $argument,
            ['\\Inventory\\StockResponse', 'decode'],
            $metadata,
            $options
        );
    }

    /**
     * @param \Inventory\ReserveRequest $argument
     * @param array $metadata
     * @param array $options
     * @return \Grpc\UnaryCall
     */
    public function EnsureProductStock(\Inventory\ReserveRequest $argument, $metadata = [], $options = [])
    {
        return $this->_simpleRequest(
            '/inventory.InventoryService/EnsureProductStock',
            $argument,
            ['\\Inventory\\ReserveResponse', 'decode'],
            $metadata,
            $options
        );
    }

    /**
     * @param \Inventory\ReserveRequest $argument
     * @param array $metadata
     * @param array $options
     * @return \Grpc\UnaryCall
     */
    public function ReserveStock(\Inventory\ReserveRequest $argument, $metadata = [], $options = [])
    {
        return $this->_simpleRequest(
            '/inventory.InventoryService/ReserveStock',
            $argument,
            ['\\Inventory\\ReserveResponse', 'decode'],
            $metadata,
            $options
        );
    }

    /**
     * @param \Inventory\ReserveRequest $argument
     * @param array $metadata
     * @param array $options
     * @return \Grpc\UnaryCall
     */
    public function CommitReservation(\Inventory\ReserveRequest $argument, $metadata = [], $options = [])
    {
        return $this->_simpleRequest(
            '/inventory.InventoryService/CommitReservation',
            $argument,
            ['\\Inventory\\ReserveResponse', 'decode'],
            $metadata,
            $options
        );
    }

    /**
     * @param \Inventory\ReserveRequest $argument
     * @param array $metadata
     * @param array $options
     * @return \Grpc\UnaryCall
     */
    public function ReleaseReservation(\Inventory\ReserveRequest $argument, $metadata = [], $options = [])
    {
        return $this->_simpleRequest(
            '/inventory.InventoryService/ReleaseReservation',
            $argument,
            ['\\Inventory\\ReserveResponse', 'decode'],
            $metadata,
            $options
        );
    }

    /**
     * @param \Inventory\RollbackRequest $argument
     * @param array $metadata
     * @param array $options
     * @return \Grpc\UnaryCall
     */
    public function RollbackStock(\Inventory\RollbackRequest $argument, $metadata = [], $options = [])
    {
        return $this->_simpleRequest(
            '/inventory.InventoryService/RollbackStock',
            $argument,
            ['\\Inventory\\RollbackResponse', 'decode'],
            $metadata,
            $options
        );
    }
}
