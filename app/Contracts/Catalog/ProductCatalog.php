<?php

namespace App\Contracts\Catalog;

interface ProductCatalog
{
    public function findById(int $productId): ?array;

    public function findByIds(array $productIds): array;

    /**
     * Read catalog snapshots without contacting the inventory service.
     *
     * This is used after stock has already been reserved, so saving the order
     * does not depend on a second availability call to the warehouse.
     */
    public function findSnapshotsByIds(array $productIds): array;

    public function ensureInventoryStock(int $productId, int $initialQuantity): void;

    /**
     * Reserve stock and return product snapshots keyed by product ID.
     */
    public function reserveStock(array $items): array;

    public function releaseStock(array $items): void;
}
