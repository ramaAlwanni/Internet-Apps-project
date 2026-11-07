<?php

namespace App\Contracts\Inventory;

interface InventoryGateway
{
    public function ensureProductStock(int $productId, int $initialQuantity): void;

    public function getAvailableQuantity(int $productId): int;

    public function reserveProduct(
        string $reservationId,
        int $productId,
        int $quantity
    ): array;

    public function commitReservation(string $reservationId): array;

    public function releaseReservation(string $reservationId): array;
}
