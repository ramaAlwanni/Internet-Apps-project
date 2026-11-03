<?php

namespace Modules\Product\Service;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Modules\Product\Exceptions\ProductImageUploadException;
use Modules\Product\Models\Product;
use Throwable;

class ProductService
{
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
        } catch (Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }

        return $product->load('category');
    }

    public function getAllProducts(array $filters = []): LengthAwarePaginator
    {
        $perPage = $filters['per_page'] ?? 10;

        return Product::with('category')
            ->when(
                $filters['category_id'] ?? null,
                fn ($query, $categoryId) => $query->where('category_id', $categoryId)
            )
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString(); // حتى روابط الصفحة التالية تحتفظ بالفلتر
    }
}

// namespace Modules\Product\Service;

// class ProductService
// {
//     public function addProduct(array $data)
//     {
//         //
//     }

//     public function getAllProducts()
//     {
//         //
//     }
// }
