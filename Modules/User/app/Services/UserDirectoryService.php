<?php

namespace Modules\User\Services;

use App\Contracts\Identity\UserDirectory;
use Modules\User\Models\User;

class UserDirectoryService implements UserDirectory
{
    public function findBasicById(int $userId): ?array
    {
        $user = User::query()->find($userId);

        if (!$user) {
            return null;
        }

        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
        ];
    }
}
