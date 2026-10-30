<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Product\Service\ProductService;
use App\Traits\ApiResponseTrait;
use Modules\Product\Http\Requests\ProductRequest;

class ProductController extends Controller
{
    use ApiResponseTrait;
    protected ProductService $product_service;

    public function __construct(ProductService $product_service)
    {
        $this->product_service = $product_service;
    }
    /////////////////////////////////////////////////////////
    public function store(ProductRequest $productRequest)
    {
        // $product = $this->product_service->addProduct($productRequest->validated());
        // return $this->successResponse('Product added successfully', $product, 200);
    }
    ///////////////////////////////////////////////////////
    public function index()
    {
        // $products = $this->product_service->getAllProducts();
        // return $this->successResponse('Products retrieved successfully', $products, 200);
    }
    ////////////////////////////////////////////////////////

}
