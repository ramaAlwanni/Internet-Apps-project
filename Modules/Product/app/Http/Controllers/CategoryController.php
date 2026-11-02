<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Modules\Product\Http\Requests\StoreCategoryRequest;
use Modules\Product\Models\Category;
use Modules\Product\Service\CategoryService;

class CategoryController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private CategoryService $service)
    {
    }

    // GET /api/categories
    public function index()
    {
        return $this->successResponse(
            'Categories retrieved successfully',
            $this->service->list()
        );
    }

    // GET /api/categories/{category}
    public function show(Category $category)
    {
        return $this->successResponse('Category retrieved successfully', $category);
    }

    // POST /api/categories
    public function store(StoreCategoryRequest $request)
    {
        $category = $this->service->create($request->validated());

        return $this->successResponse('Category created successfully', $category, 201);
    }

    // PUT /api/categories/{category}
    public function update(StoreCategoryRequest $request, Category $category)
    {
        $category = $this->service->update($category, $request->validated());

        return $this->successResponse('Category updated successfully', $category);
    }

    // DELETE /api/categories/{category}
    public function destroy(Category $category)
    {
        $this->service->delete($category);

        return $this->successResponse('Category deleted successfully');
    }
}