<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['view key control history', 'manage key control administration'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'Gestão de porteiro', 'guard_name' => 'web'])
            ->syncPermissions(['view key control', 'view-any key control', 'view key control history', 'export key control']);

        Role::firstOrCreate(['name' => 'Admin Porteiro', 'guard_name' => 'web'])
            ->syncPermissions([
                'view key control',
                'view-any key control',
                'view key control history',
                'view key control pending returns',
                'register key control pending return',
                'manage key control settings',
                'manage key control report recipients',
                'export key control',
                'manage key control administration',
            ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::whereIn('name', ['Gestão de porteiro', 'Admin Porteiro'])->where('guard_name', 'web')->delete();
        Permission::whereIn('name', ['view key control history', 'manage key control administration'])->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
