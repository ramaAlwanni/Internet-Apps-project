<?php

namespace Modules\Product\Service;

use App\Exceptions\CategoryHasProductsException as ExceptionsCategoryHasProductsException;
use Illuminate\Database\Eloquent\Collection;
use Modules\Product\Exceptions\CategoryHasProductsException;
use Modules\Product\Models\Category;

class CategoryService
{
    public function list(): Collection
    {
        return Category::orderBy('name')->get();
    }

    public function create(array $data): Category
    {
        return Category::create($data);
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category;
    }

    public function delete(Category $category): void
    {
        if ($category->products()->exists()) {
            throw new ExceptionsCategoryHasProductsException();
        }

        $category->delete();
    }
}