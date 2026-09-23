<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::where('name', 'Gestão de Chaves')
            ->where('guard_name', 'web')
            ->first()?->revokePermissionTo('correct key control');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::where('name', 'Gestão de Chaves')
            ->where('guard_name', 'web')
            ->first()?->givePermissionTo('correct key control');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
