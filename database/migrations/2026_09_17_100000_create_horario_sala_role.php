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

        $permission = Permission::firstOrCreate([
            'name' => 'view room merged schedule',
            'guard_name' => 'web',
        ]);

        $role = Role::firstOrCreate([
            'name' => 'Horário/Sala',
            'guard_name' => 'web',
        ]);

        if (! $role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::where([
            'name' => 'Horário/Sala',
            'guard_name' => 'web',
        ])->first();

        if ($role) {
            $role->revokePermissionTo('view room merged schedule');
            $role->delete();
        }

        Permission::where([
            'name' => 'view room merged schedule',
            'guard_name' => 'web',
        ])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
