<?php

namespace Modules\Product\Observers;

use Modules\Product\Models\Product;
use Modules\Product\Service\ProductCacheService;

class ProductObserver 
{
    protected ProductCacheService $cacheService;

    public function __construct(ProductCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    public function saved(Product $product): void
    {
        $this->cacheService->invalidateCache();
    }

    public function deleted(Product $product): void
    {
        $this->cacheService->invalidateCache();
    }
    
}