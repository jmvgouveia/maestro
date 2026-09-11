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
            'view unrestricted merged schedule',
        ];

        $secretariaPermissions = [
            'view Student', 'view-any Student', 'create Student', 'update Student', 'delete Student', 'delete-any Student',
            'view Registration', 'view-any Registration', 'create Registration', 'update Registration', 'delete Registration', 'delete-any Registration',
            'view RegistrationSubject', 'view-any RegistrationSubject', 'create RegistrationSubject', 'update RegistrationSubject', 'delete RegistrationSubject', 'delete-any RegistrationSubject',
            'view Course', 'view-any Course',
            'view Classes', 'view-any Classes',
            'view Subject', 'view-any Subject',
            'view TeacherSubject', 'view-any TeacherSubject', 'create TeacherSubject', 'update TeacherSubject', 'delete TeacherSubject', 'delete-any TeacherSubject',
            'view CourseSubject', 'view-any CourseSubject', 'create CourseSubject', 'update CourseSubject', 'delete CourseSubject', 'delete-any CourseSubject',
        ];

        $areaPedagogicaPermissions = [
            'view Course', 'view-any Course',
            'view CourseSubject', 'view-any CourseSubject',
            'view Subject', 'view-any Subject',
            'view Classes', 'view-any Classes',
            'view Student', 'view-any Student',
            'view Registration', 'view-any Registration',
            'view Teacher', 'view-any Teacher',
            'view TeacherSubject', 'view-any TeacherSubject',
            'view Schedule', 'view-any Schedule',
            'view Room', 'view-any Room',
            'view Building', 'view-any Building',
            'view Timeperiod', 'view-any Timeperiod',
            'view Weekday', 'view-any Weekday',
            'view SchoolYear', 'view-any SchoolYear',
            'view Department', 'view-any Department',
            'view ScheduleRequest', 'view-any ScheduleRequest',
            'create Course', 'update Course',
            'create CourseSubject', 'update CourseSubject',
            'create Subject', 'update Subject',
            'create Classes', 'update Classes',
            'create TeacherSubject', 'update TeacherSubject',
            'create Schedule', 'update Schedule',
            'delete Schedule', 'delete-any Schedule',
        ];

        $recursosHumanosPermissions = [
            'view Teacher', 'view-any Teacher', 'create Teacher', 'update Teacher',
            'view User', 'view-any User', 'create User', 'update User',
            'view ScheduleRequest', 'view-any ScheduleRequest',
            'manage user activation',
        ];

        $resourcePermissions = [];
        foreach ([
            'Building', 'Classes', 'ContratualRelationship', 'Course', 'CourseSubject',
            'Department', 'Gender', 'Nationality', 'Permission', 'Position',
            'ProfessionalRelationship', 'Qualification', 'Registration', 'Role',
            'Room', 'RoomBlockedHours', 'SalaryScale', 'Schedule', 'ScheduleRequest',
            'SchoolYear', 'Student', 'Subject', 'Teacher', 'TeacherHourCounter',
            'TeacherSubject', 'RegistrationSubject', 'TimeReduction', 'Timeperiod', 'User', 'Weekday', 'EmailAudit', 'HelpArticle',
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
            'Área Pedagógica' => $areaPedagogicaPermissions,
            'Horário Sobreposto' => [
                'view unrestricted merged schedule',
            ],
            'Recursos Humanos' => $recursosHumanosPermissions,
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
