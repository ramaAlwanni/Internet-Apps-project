<?php

namespace Database\Seeders;

use App\Services\PermissionSyncService;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(PermissionSyncService $permissionSyncService): void
    {
        $permissionSyncService->syncPermissions();
    }
}
