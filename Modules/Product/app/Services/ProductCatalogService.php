<?php

namespace Modules\Product\Services;

use App\Contracts\Catalog\ProductCatalog;
use Modules\Product\Models\Product;
use RuntimeException;

class ProductCatalogService implements ProductCatalog
{
    public function findById(int $productId): ?array
    {
        $product = Product::query()->find($productId);

        return $product
            ? $this->snapshot($product)
            : null;
    }

    public function findByIds(array $productIds): array
    {
        return Product::query()
            ->whereIn('id', array_map('intval', $productIds))
            ->get()
            ->mapWithKeys(fn (Product $product) => [
                (int) $product->id => $this->snapshot($product),
            ])
            ->all();
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
}
