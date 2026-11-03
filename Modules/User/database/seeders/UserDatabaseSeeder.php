<?php

namespace Modules\User\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\User\Models\User;

class UserDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@example.test',
                'role' => 'admin',
            ],
            [
                'name' => 'Customer One',
                'email' => 'customer1@example.test',
                'role' => 'user',
            ],
            [
                'name' => 'Customer Two',
                'email' => 'customer2@example.test',
                'role' => 'user',
            ],
            [
                'name' => 'Customer Three',
                'email' => 'customer3@example.test',
                'role' => 'user',
            ],
        ];

        foreach ($users as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => 'password',
                ],
            );

            $user->syncRoles($data['role']);
        }
    }
}
