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
            'operate key control',
            'view key control',
            'view-any key control',
            'correct key control',
            'manage user room authorizations',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        Role::firstOrCreate([
            'name' => 'Porteiro',
            'guard_name' => 'web',
        ])->syncPermissions(['operate key control']);

        Role::firstOrCreate([
            'name' => 'Gestão de Chaves',
            'guard_name' => 'web',
        ])->syncPermissions([
            'view key control',
            'view-any key control',
            'correct key control',
            'manage user room authorizations',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::where('name', 'Porteiro')->where('guard_name', 'web')->delete();
        Role::where('name', 'Gestão de Chaves')->where('guard_name', 'web')->delete();

        Permission::whereIn('name', [
            'operate key control',
            'view key control',
            'view-any key control',
            'correct key control',
            'manage user room authorizations',
        ])->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
