<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::create('key_control_role_transfers', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
        });
        Schema::create('key_control_permission_grants', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        foreach ([
            'view key control occupancy map',
            'manage room occupancy settings',
        ] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $porter = Role::firstOrCreate(['name' => 'Porteiro', 'guard_name' => 'web']);
        $management = Role::firstOrCreate(['name' => 'Gestão de Chaves', 'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'Admin Porteiro', 'guard_name' => 'web']);

        $legacyManagement = Role::where('name', 'Gestão de porteiro')->where('guard_name', 'web')->first();

        if ($legacyManagement !== null) {
            foreach ($legacyManagement->users as $user) {
                if ($user->hasRole($management)) {
                    continue;
                }

                DB::table('key_control_role_transfers')->insert([
                    'user_id' => $user->getKey(),
                ]);
                $user->assignRole($management);
                $user->removeRole($legacyManagement);
            }
        }

        $grantPermissions = function (Role $role, array $permissions): void {
            foreach ($permissions as $permissionName) {
                $permission = Permission::findByName($permissionName, 'web');

                if ($role->hasPermissionTo($permission)) {
                    continue;
                }

                DB::table('key_control_permission_grants')->insert([
                    'role_id' => $role->getKey(),
                    'permission_id' => $permission->getKey(),
                ]);
                $role->givePermissionTo($permission);
            }
        };

        $grantPermissions($porter, [
            'operate key control',
            'view key control',
            'view-any key control',
            'view key control pending returns',
            'register key control pending return',
            'view key control occupancy map',
        ]);

        $grantPermissions($management, [
            'view key control',
            'view-any key control',
            'view key control history',
            'export key control',
            'view key control occupancy map',
        ]);

        $grantPermissions($admin, [
            'view key control',
            'view-any key control',
            'view key control history',
            'export key control',
            'view key control occupancy map',
            'correct key control',
            'manage key control settings',
            'manage key control report recipients',
            'manage key control administration',
            'manage user room authorizations',
            'manage room occupancy settings',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $legacyManagement = Role::where('name', 'Gestão de porteiro')
            ->where('guard_name', 'web')
            ->first();
        $management = Role::where('name', 'Gestão de Chaves')
            ->where('guard_name', 'web')
            ->first();

        if ($legacyManagement !== null && $management !== null && Schema::hasTable('key_control_role_transfers')) {
            $userIds = DB::table('key_control_role_transfers')->pluck('user_id');

            foreach ($userIds as $userId) {
                $user = User::find($userId);

                if ($user === null) {
                    continue;
                }

                if ($user->hasRole($management)) {
                    $user->removeRole($management);
                }
                $user->assignRole($legacyManagement);
            }

        }

        if (Schema::hasTable('key_control_permission_grants')) {
            foreach (DB::table('key_control_permission_grants')->get() as $grant) {
                $role = Role::find($grant->role_id);
                $permission = Permission::find($grant->permission_id);

                if ($role !== null && $permission !== null) {
                    $role->revokePermissionTo($permission);
                }
            }
        }

        Schema::dropIfExists('key_control_role_transfers');
        Schema::dropIfExists('key_control_permission_grants');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
