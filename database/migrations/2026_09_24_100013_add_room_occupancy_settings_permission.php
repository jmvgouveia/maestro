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
        Permission::firstOrCreate(['name' => 'manage room occupancy settings', 'guard_name' => 'web']);
        Role::where('name', 'Admin Porteiro')->where('guard_name', 'web')->first()?->givePermissionTo('manage room occupancy settings');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::where('name', 'Admin Porteiro')->where('guard_name', 'web')->first()?->revokePermissionTo('manage room occupancy settings');
        Permission::where('name', 'manage room occupancy settings')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
