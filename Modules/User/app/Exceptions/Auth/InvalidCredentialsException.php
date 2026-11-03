<?php

namespace Modules\User\Exceptions\Auth;

use App\Exceptions\BaseException;

class InvalidCredentialsException extends BaseException 
{
    public function __construct()
    {
        parent::__construct('Email or password not correct!', 401);
    }
}
