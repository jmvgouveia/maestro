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

        $roles = [
            'Coordenador de Formação Musical' => 'view formacao musical coordination',
            'Coordenador de Iniciação Musical' => 'view iniciacao musical coordination',
        ];

        foreach ($roles as $roleName => $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);

            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            ['Coordenador de Formação Musical', 'view formacao musical coordination'],
            ['Coordenador de Iniciação Musical', 'view iniciacao musical coordination'],
        ] as [$roleName, $permissionName]) {
            Role::where('name', $roleName)->where('guard_name', 'web')->delete();
            Permission::where('name', $permissionName)->where('guard_name', 'web')->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
