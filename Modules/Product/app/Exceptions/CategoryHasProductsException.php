<?php

namespace Modules\Product\Exceptions;

use App\Exceptions\BaseException;

class CategoryHasProductsException extends BaseException
{
    public function __construct()
    {
        parent::__construct(
            'Cannot delete this category because it still has products. Move or delete its products first.',
            409
        );
    }
}