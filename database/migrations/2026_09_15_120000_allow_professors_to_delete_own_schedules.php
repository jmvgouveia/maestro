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

        $permission = Permission::findOrCreate('delete Schedule', 'web');
        $professor = Role::query()
            ->where('name', 'Professor')
            ->where('guard_name', 'web')
            ->first();

        $professor?->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permission = Permission::query()
            ->where('name', 'delete Schedule')
            ->where('guard_name', 'web')
            ->first();

        $professor = Role::query()
            ->where('name', 'Professor')
            ->where('guard_name', 'web')
            ->first();

        if ($permission && $professor) {
            $professor->revokePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
