<?php

namespace App\Rules;

use App\Contracts\Catalog\ProductCatalog;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ProductExists implements ValidationRule
{
    public function __construct(
        private readonly ProductCatalog $productCatalog
    ) {
    }

    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        if (!$this->productCatalog->findById((int) $value)) {
            $fail('The selected product is invalid.');
        }
    }
}

