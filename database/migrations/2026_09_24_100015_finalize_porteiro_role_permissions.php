<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::where('name', 'Gestão de porteiro')->where('guard_name', 'web')->first()?->syncPermissions([
            'view key control',
            'view-any key control',
            'view key control history',
            'export key control',
        ]);

        Role::where('name', 'Admin Porteiro')->where('guard_name', 'web')->first()?->revokePermissionTo([
            'view key control pending returns',
            'register key control pending return',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::where('name', 'Admin Porteiro')->where('guard_name', 'web')->first()?->givePermissionTo([
            'view key control pending returns',
            'register key control pending return',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
