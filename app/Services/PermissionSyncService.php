<?php

namespace App\Services;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\Config;

class PermissionSyncService
{
    public function syncPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = Config::get('permissions.permissions', []);
        $roles = Config::get('permissions.roles', []);
foreach ($permissions as $permission) {
    Permission::firstOrCreate([
        'name' => $permission,
        'guard_name' => 'api',
    ]);
}

foreach ($roles as $roleName => $permissions) {
    $role = Role::firstOrCreate([
        'name' => $roleName,
        'guard_name' => 'api',
    ]);

    if ($roleName === 'super-admin') {
        $role->syncPermissions(Permission::where('guard_name', 'api')->get());
    } else {
        $role->syncPermissions($permissions);
    }
}

    }
}