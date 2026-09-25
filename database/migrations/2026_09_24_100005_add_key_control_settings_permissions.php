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
            'manage key control settings',
            'manage key control report recipients',
            'view key control pending returns',
            'register key control pending return',
            'release key control room',
            'open room with floor key',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        Role::where('name', 'Porteiro')->where('guard_name', 'web')->first()?->givePermissionTo([
            'operate key control',
            'view key control',
            'view-any key control',
            'view key control pending returns',
            'register key control pending return',
            'release key control room',
            'open room with floor key',
        ]);

        Role::where('name', 'Gestão de Chaves')->where('guard_name', 'web')->first()?->givePermissionTo([
            'view key control',
            'view-any key control',
            'export key control',
            'manage key control settings',
            'manage key control report recipients',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::where('name', 'Porteiro')->where('guard_name', 'web')->first()?->revokePermissionTo([
            'view key control pending returns',
            'register key control pending return',
            'release key control room',
            'open room with floor key',
        ]);

        Role::where('name', 'Gestão de Chaves')->where('guard_name', 'web')->first()?->revokePermissionTo([
            'manage key control settings',
            'manage key control report recipients',
        ]);

        Permission::whereIn('name', [
            'manage key control settings',
            'manage key control report recipients',
            'view key control pending returns',
            'register key control pending return',
            'release key control room',
            'open room with floor key',
        ])->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
