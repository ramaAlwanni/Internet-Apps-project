<?php

namespace App\Contracts\Identity;

interface UserDirectory
{
    public function findBasicById(int $userId): ?array;
}
