<?php

namespace Modules\Order\Contracts;

interface StockManager
{
    /**
     * حجز كل كميات الطلب دفعة واحدة.
     */
    public function reserve(
        string $reservationId,
        array $items
    ): array;

    /**
     * تثبيت الحجز بعد إنشاء الطلب.
     */
    public function commit(
        string $reservationId
    ): array;

    /**
     * إعادة الكمية في حال فشل إنشاء الطلب أو إلغاء الطلب.
     */
    public function release(
        string $reservationId
    ): array;
}
