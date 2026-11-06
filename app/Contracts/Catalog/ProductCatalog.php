<?php

namespace App\Contracts\Catalog;

interface ProductCatalog
{
    public function findById(int $productId): ?array;

    public function findByIds(array $productIds): array;

    public function ensureInventoryStock(int $productId, int $initialQuantity): void;

    /**
     * Reserve stock and return product snapshots keyed by product ID.
     */
    public function reserveStock(array $items): array;

    public function releaseStock(array $items): void;
}
