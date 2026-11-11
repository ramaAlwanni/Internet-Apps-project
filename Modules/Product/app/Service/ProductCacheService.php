<?php

namespace Modules\Product\Service;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Modules\Product\Models\Product;

class ProductCacheService
{
    protected const VERSION_KEY = 'products:cache:version';
    protected const TTL = 3600;

    /**
     * Fetch the current cache version. If it doesn't exist, initialize it to 1. This versioning allows for efficient cache invalidation without needing to clear all cached entries manually.
     */
    protected function getVersion(): int
    {
        return Cache::rememberForever(self::VERSION_KEY, fn() => 1);
    }

    /**
     * Fetch the cached products with filters and pagination using the version.
     */
    public function getCachedProducts(array $filters = []): LengthAwarePaginator
    {
        $version = $this->getVersion();
        $perPage = $filters['per_page'] ?? 10;
        $page = request()->get('page', 1);
        $categoryId = $filters['category_id'] ?? 'all';

        $cacheKey = "products:v{$version}:cat_{$categoryId}:page_{$page}:per_{$perPage}";

        if (Cache::has($cacheKey)) {
            \Illuminate\Support\Facades\Log::info("[CACHE HIT] Fetched from Redis using key: {$cacheKey}");
        } else {
            \Illuminate\Support\Facades\Log::info("[CACHE MISS] Fetched from Database and will be cached. Key: {$cacheKey}");
        }

        return Cache::remember($cacheKey, self::TTL, function () use ($filters, $perPage) {
            return Product::with('category')
                ->when(
                    $filters['category_id'] ?? null,
                    fn($query, $categoryId) => $query->where('category_id', $categoryId)
                )
                ->latest('id')
                ->paginate($perPage)
                ->withQueryString();
        });
    }

    /**
     * Invalidate the cache by incrementing the version number.
     */
    public function invalidateCache(): void
    {
        if (!Cache::has(self::VERSION_KEY)) {
            Cache::forever(self::VERSION_KEY, 1);
        } else {
            Cache::increment(self::VERSION_KEY);
        }
    }

}
