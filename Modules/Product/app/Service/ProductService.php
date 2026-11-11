<?php

namespace Modules\Product\Service;

use App\Contracts\Catalog\ProductCatalog;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Modules\Product\Exceptions\ProductImageUploadException;
use Modules\Product\Models\Product;
use Throwable;

class ProductService
{
    public function __construct(
        private readonly ProductCatalog $productCatalog,
        private readonly ProductCacheService $productCacheService 
    ) {
    }

    public function addProduct(array $data): Product
    {
        // 1) خزّني الصورة بـ storage/app/public/products
        $path = $data['photo']->store('products', 'public');

        if (! $path) {
            throw new ProductImageUploadException();
        }

        $data['photo'] = $path;

        // 2) أنشئي المنتج. إذا فشل، احذفي الصورة حتى ما تبقى ملفات يتيمة
        try {
            $product = Product::create($data);
            $this->productCatalog->ensureInventoryStock(
                (int) $product->id,
                (int) $product->stock
            );
        } catch (Throwable $e) {
            if (isset($product)) {
                $product->delete();
            }
            Storage::disk('public')->delete($path);
            throw $e;
        }

        return $product->load('category');
    }

    public function getAllProducts(array $filters = []): LengthAwarePaginator
    {
        //Get product with cache:
        $products = $this->productCacheService->getCachedProducts($filters);

        $productIds = $products->getCollection()->pluck('id')->all();

        if (!empty($productIds)) {
            $snapshots = $this->productCatalog->findByIds($productIds);

            $products->getCollection()->each(function (Product $product) use ($snapshots): void {
                if (isset($snapshots[(int) $product->id])) {
                    $product->setAttribute('stock', $snapshots[(int) $product->id]['stock']);
                }
            });
        }

        //get product without cache:
        // $perPage = $filters['per_page'] ?? 10;

        // $products = Product::with('category')
        //     ->when(
        //         $filters['category_id'] ?? null,
        //         fn ($query, $categoryId) => $query->where('category_id', $categoryId)
        //     )
        //     ->latest('id')
        //     ->paginate($perPage)
        //     ->withQueryString(); // حتى روابط الصفحة التالية تحتفظ بالفلتر

        // $snapshots = $this->productCatalog->findByIds(
        //     $products->getCollection()->pluck('id')->all()
        // );
        // $products->getCollection()->each(function (Product $product) use ($snapshots): void {
        //     if (isset($snapshots[(int) $product->id])) {
        //         $product->setAttribute('stock', $snapshots[(int) $product->id]['stock']);
        //     }
        // });

        return $products;
    }
}
