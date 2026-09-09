<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Limpar o cache de permissões
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 🔧 Permissões fixas manuais (não ligadas a resources)
        $customPermissions = [
            'aprovar trocas',
            'ver relatórios',
            'view teacher students',
            'view Schedule',
            'view-any Schedule',
            'create Schedule',
            'view TeacherSubject',
            'view-any TeacherSubject',
            'view ScheduleRequest',
            'view-any ScheduleRequest',
            'update ScheduleRequest',
            'manage user activation',
        ];

        $secretariaPermissions = [
            'view Registration', 'view-any Registration', 'create Registration', 'update Registration', 'delete Registration', 'delete-any Registration',
            'view TeacherSubject', 'view-any TeacherSubject', 'create TeacherSubject', 'update TeacherSubject', 'delete TeacherSubject', 'delete-any TeacherSubject',
            'view CourseSubject', 'view-any CourseSubject', 'create CourseSubject', 'update CourseSubject', 'delete CourseSubject', 'delete-any CourseSubject',
        ];

        $resourcePermissions = [];
        foreach ([
            'Building', 'Classes', 'ContratualRelationship', 'Course', 'CourseSubject',
            'Department', 'Gender', 'Nationality', 'Permission', 'Position',
            'ProfessionalRelationship', 'Qualification', 'Registration', 'Role',
            'Room', 'RoomBlockedHours', 'SalaryScale', 'Schedule', 'ScheduleRequest',
            'SchoolYear', 'Student', 'Subject', 'Teacher', 'TeacherHourCounter',
            'TeacherSubject', 'TimeReduction', 'Timeperiod', 'User', 'Weekday', 'EmailAudit',
        ] as $model) {
            foreach (['view', 'view-any', 'create', 'update', 'delete', 'delete-any', 'restore', 'restore-any', 'replicate', 'reorder', 'force-delete', 'force-delete-any'] as $ability) {
                $resourcePermissions[] = "{$ability} {$model}";
            }
        }

        // 🧱 Criar roles e associar permissões
        $roles = [
            'Super Admin' => [],
            'Professor' => [
                'view_schedule',
                'create_schedule',
                'create Schedule',
                'view Schedule',
                'view-any Schedule',
                'update Schedule',
                'view TeacherSubject',
                'view-any TeacherSubject',
                'view ScheduleRequest',
                'view-any ScheduleRequest',
                'update ScheduleRequest',
                'aprovar trocas',
                'view teacher students',
            ],
            'Gestor Conflitos' => [
                'view Schedule',
                'view-any Schedule',
                'update Schedule',
                'view ScheduleRequest',
                'view-any ScheduleRequest',
                'update ScheduleRequest',
                'aprovar trocas',
            ],
            'Recursos Humanos' => [
                'view_any_user',
                'create_user',
                'update_user',
                'delete_user',
                'manage user activation',
            ],
            'Aluno' => [],
            'Secretaria' => $secretariaPermissions,
        ];

        foreach (array_unique(array_merge($resourcePermissions, $customPermissions, ...array_values($roles))) as $permission) {
            Permission::firstOrCreate(['name' => $permission], ['guard_name' => 'web']);
        }

        $roles['Super Admin'] = Permission::query()->pluck('name')->all();

        foreach ($roles as $role => $permissions) {
            $roleModel = Role::firstOrCreate(['name' => $role]);
            $roleModel->syncPermissions($permissions);
        }

        // 👤 Atribuir Super Admin ao primeiro utilizador (opcional)
        $user = User::first();
        if ($user && ! $user->hasRole('Super Admin')) {
            $user->assignRole('Super Admin');
        }
    }
}
