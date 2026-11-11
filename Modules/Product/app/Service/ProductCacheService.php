<?php

namespace Modules\Product\Service;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Modules\Product\Models\Product;

class ProductCacheService 
{
    protected const VERSION_KEY = 'products:cache:version';
    protected const TTL = 3600; // ساعة واحدة

    /**
     * الحصول على رقم الإصدار الحالي للكاش (يبدأ بـ 1 افتراضياً)
     */
    protected function getVersion(): int
    {
        return Cache::rememberForever(self::VERSION_KEY, fn() => 1);
    }

    /**
     * جلب المنتجات المفعلة مع الفلاتر والتنقل بين الصفحات باستخدام الإصدار
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
     * إبطال الكاش هندسياً (Senior Way) عبر زيادة الإصدار فقط
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