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
