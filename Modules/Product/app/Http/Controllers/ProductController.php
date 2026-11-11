<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Product\Http\Requests\ProductRequest;
use Modules\Product\Models\Product;
use Modules\Product\Service\ProductService;

class ProductController extends Controller
{
    use ApiResponseTrait;

    protected ProductService $product_service;

    public function __construct(ProductService $product_service)
    {
        $this->product_service = $product_service;
    }

    // POST /api/v1/products
    public function store(ProductRequest $productRequest)
    {
        $product = $this->product_service->addProduct($productRequest->validated());

        return $this->successResponse('Product added successfully', $product, 201);
    }

    // GET /api/v1/products?category_id=1&per_page=10&page=1
    public function index(Request $request)
    {
        $filters = $request->validate([
            'category_id' => 'sometimes|integer|exists:categories,id',
            'per_page'    => 'sometimes|integer|min:1|max:50',
        ]);

        $products = $this->product_service->getAllProducts($filters);

        return $this->successResponse('Products retrieved successfully', $products);
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $product->delete();
        return $this->successResponse('Product deleted successfully', null, 200);
    }
}
