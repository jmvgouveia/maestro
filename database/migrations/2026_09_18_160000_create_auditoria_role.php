<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'view access audit',
            'view students without schedule audit',
            'view teacher subject shift audit',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        Role::firstOrCreate([
            'name' => 'Auditoria',
            'guard_name' => 'web',
        ])->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::where('name', 'Auditoria')->where('guard_name', 'web')->delete();
        Permission::whereIn('name', [
            'view access audit',
            'view students without schedule audit',
            'view teacher subject shift audit',
        ])->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
