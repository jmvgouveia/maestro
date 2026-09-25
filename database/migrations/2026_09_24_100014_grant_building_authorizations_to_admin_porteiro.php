<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::where('name', 'Admin Porteiro')->where('guard_name', 'web')->first()?->givePermissionTo('manage user room authorizations');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::where('name', 'Admin Porteiro')->where('guard_name', 'web')->first()?->revokePermissionTo('manage user room authorizations');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
