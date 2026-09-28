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

        $permissions = collect(['view key control', 'view-any key control'])
            ->map(fn (string $name): Permission => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]));

        Role::where('name', 'Porteiro')
            ->where('guard_name', 'web')
            ->first()?->givePermissionTo($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::where('name', 'Porteiro')
            ->where('guard_name', 'web')
            ->first()?->revokePermissionTo(['view key control', 'view-any key control']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
