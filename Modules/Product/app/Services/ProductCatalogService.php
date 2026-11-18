<?php

namespace Modules\Product\Services;

use App\Contracts\Catalog\ProductCatalog;
use App\Contracts\Inventory\InventoryGateway;
use App\Exceptions\InventoryUnavailableException;
use Illuminate\Support\Facades\Log;
use Modules\Product\Models\Product;
use RuntimeException;
use Throwable;

class ProductCatalogService implements ProductCatalog
{
    public function __construct(
        private readonly InventoryGateway $inventory
    ) {
    }

    public function findById(int $productId): ?array
    {
        $product = Product::query()->find($productId);

        return $product
            ? $this->snapshotWithInventory($product)
            : null;
    }

    public function findByIds(array $productIds): array
    {
        $products = Product::query()
            ->whereIn('id', array_map('intval', $productIds))
            ->get();

        $snapshots = [];

        // Read one product at a time and stop at the first inventory failure.
        // Without this fail-fast loop a slow/dead warehouse service could cost
        // one full timeout per product (and two RPC calls per product).
        foreach ($products as $product) {
            $snapshots[(int) $product->id] = $this->snapshotWithInventory($product);
        }

        return $snapshots;
    }

    public function findSnapshotsByIds(array $productIds): array
    {
        return Product::query()
            ->whereIn('id', array_map('intval', $productIds))
            ->get()
            ->mapWithKeys(fn (Product $product) => [
                (int) $product->id => $this->snapshot($product),
            ])
            ->all();
    }

    public function ensureInventoryStock(int $productId, int $initialQuantity): void
    {
        if ($this->usesGrpcInventory()) {
            $this->inventory->ensureProductStock($productId, $initialQuantity);
        }
    }

    public function reserveStock(array $items): array
    {
        $productIds = collect($items)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($products->count() !== $productIds->count()) {
            throw new RuntimeException(
                'One or more products were not found.'
            );
        }

        foreach ($items as $item) {
            $product = $products->get((int) $item['product_id']);
            $quantity = (int) $item['quantity'];

            if ($quantity < 1) {
                throw new RuntimeException(
                    'Quantity must be greater than zero.'
                );
            }

            if ($product->stock < $quantity) {
                throw new RuntimeException(
                    "Insufficient stock for product: {$product->name}"
                );
            }
        }

        foreach ($items as $item) {
            $products
                ->get((int) $item['product_id'])
                ->decrement('stock', (int) $item['quantity']);
        }

        return $products
            ->mapWithKeys(fn (Product $product) => [
                (int) $product->id => $this->snapshot($product),
            ])
            ->all();
    }

    public function releaseStock(array $items): void
    {
        $productIds = collect($items)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($items as $item) {
            $product = $products->get((int) $item['product_id']);

            if (!$product) {
                throw new RuntimeException(
                    'One or more products were not found.'
                );
            }

            $product->increment(
                'stock',
                (int) $item['quantity']
            );
        }
    }

    private function snapshot(Product $product): array
    {
        return [
            'id' => (int) $product->id,
            'name' => (string) $product->name,
            'price' => (float) $product->price,
            'stock' => (int) $product->stock,
            'photo_url' => $product->photo_url,
        ];
    }

    private function snapshotWithInventory(Product $product): array
    {
        $snapshot = $this->snapshot($product);
        if (!$this->usesGrpcInventory()) {
            return $snapshot;
        }

        $productId = (int) $product->id;

        try {
            $this->inventory->ensureProductStock($productId, (int) $product->stock);
            $snapshot['stock'] = $this->inventory->getAvailableQuantity($productId);
        } catch (InventoryUnavailableException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Unable to read product stock from the inventory service.', [
                'product_id' => $productId,
                'exception' => $exception,
            ]);

            throw new InventoryUnavailableException(
                InventoryUnavailableException::DEFAULT_MESSAGE,
                $exception
            );
        }

        return $snapshot;
    }

    private function usesGrpcInventory(): bool
    {
        return config('order.stock_manager', 'local') === 'grpc';
    }
}
