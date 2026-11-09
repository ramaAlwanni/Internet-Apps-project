<?php

namespace Modules\Product\Exceptions;

use App\Exceptions\BaseException;

class ProductImageUploadException extends BaseException
{
    public function __construct()
    {
        parent::__construct('Failed to upload the product photo. Please try again.', 500);
    }
}