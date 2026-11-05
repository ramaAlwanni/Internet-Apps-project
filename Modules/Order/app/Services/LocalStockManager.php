<?php

namespace Modules\Order\Services;

use App\Contracts\Catalog\ProductCatalog;
use Illuminate\Support\Facades\DB;
use Modules\Order\Contracts\StockManager;
use RuntimeException;

class LocalStockManager implements StockManager
{
    public function __construct(
        private readonly ProductCatalog $productCatalog
    ) {
    }

    public function reserve(
        string $reservationId,
        array $items
    ): array {
        return DB::transaction(function () use ($reservationId, $items) {
            $existingReservation = DB::table('stock_reservations')
                ->where('id', $reservationId)
                ->first();

            if ($existingReservation) {
                if ($existingReservation->status === 'reserved') {
                    return [
                        'success' => true,
                        'message' => 'Stock already reserved',
                        'reservation_id' => $reservationId,
                    ];
                }

                throw new RuntimeException(
                    'This reservation cannot be used.'
                );
            }

            $this->productCatalog->reserveStock($items);

            DB::table('stock_reservations')->insert([
                'id' => $reservationId,
                'status' => 'reserved',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($items as $item) {
                DB::table('stock_reservation_items')->insert([
                    'reservation_id' => $reservationId,
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (int) $item['quantity'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return [
                'success' => true,
                'message' => 'Stock reserved successfully',
                'reservation_id' => $reservationId,
            ];
        });
    }

    public function commit(string $reservationId): array
    {
        $updated = DB::table('stock_reservations')
            ->where('id', $reservationId)
            ->where('status', 'reserved')
            ->update([
                'status' => 'committed',
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            throw new RuntimeException(
                'Stock reservation was not found or already processed.'
            );
        }

        return [
            'success' => true,
            'message' => 'Stock reservation committed',
            'reservation_id' => $reservationId,
        ];
    }

    public function release(string $reservationId): array
    {
        return DB::transaction(function () use ($reservationId) {
            $reservation = DB::table('stock_reservations')
                ->where('id', $reservationId)
                ->lockForUpdate()
                ->first();

            if (!$reservation) {
                return [
                    'success' => true,
                    'message' => 'Reservation was not found',
                ];
            }

            if ($reservation->status === 'released') {
                return [
                    'success' => true,
                    'message' => 'Reservation already released',
                ];
            }

            if ($reservation->status === 'committed') {
                throw new RuntimeException(
                    'Committed stock cannot be released.'
                );
            }

            $items = DB::table('stock_reservation_items')
                ->where('reservation_id', $reservationId)
                ->get()
                ->map(fn ($item) => [
                    'product_id' => (int) $item->product_id,
                    'quantity' => (int) $item->quantity,
                ])
                ->all();

            $this->productCatalog->releaseStock($items);

            DB::table('stock_reservations')
                ->where('id', $reservationId)
                ->update([
                    'status' => 'released',
                    'updated_at' => now(),
                ]);

            return [
                'success' => true,
                'message' => 'Stock released successfully',
                'reservation_id' => $reservationId,
            ];
        });
    }
}
