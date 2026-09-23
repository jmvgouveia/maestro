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

        $viewPermission = Permission::firstOrCreate(['name' => 'view key control', 'guard_name' => 'web']);
        $viewAnyPermission = Permission::firstOrCreate(['name' => 'view-any key control', 'guard_name' => 'web']);
        $exportPermission = Permission::firstOrCreate(['name' => 'export key control', 'guard_name' => 'web']);

        Role::where('name', 'Porteiro')->where('guard_name', 'web')->first()?->givePermissionTo([
            $viewPermission,
            $viewAnyPermission,
        ]);

        Role::where('name', 'Gestão de Chaves')->where('guard_name', 'web')->first()?->givePermissionTo($exportPermission);
        Role::where('name', 'Gestão de Chaves')->where('guard_name', 'web')->first()?->revokePermissionTo('correct key control');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::where('name', 'Porteiro')->where('guard_name', 'web')->first()?->revokePermissionTo([
            'view key control',
            'view-any key control',
        ]);

        Role::where('name', 'Gestão de Chaves')->where('guard_name', 'web')->first()?->revokePermissionTo('export key control');
        Permission::where('name', 'export key control')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
