<?php

namespace Tests\Feature\Security;

use App\Filament\Resources\ScheduleResource;
use App\Models\Schedule;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Policies\SchedulePolicy;
use App\Policies\StudentPolicy;
use App\Policies\TeacherPolicy;
use App\Policies\UserPolicy;
use Filament\Panel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_professor_can_only_view_and_update_own_teacher_record(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Professor'));

        $ownTeacher = new Teacher(['id_user' => $user->id]);
        $otherTeacher = new Teacher(['id_user' => $user->id + 1]);
        $policy = new TeacherPolicy;

        $this->assertTrue($policy->view($user, $ownTeacher));
        $this->assertTrue($policy->update($user, $ownTeacher));
        $this->assertFalse($policy->view($user, $otherTeacher));
        $this->assertFalse($policy->update($user, $otherTeacher));
    }

    public function test_aluno_can_only_view_and_update_own_student_record(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Aluno'));

        $ownStudent = new Student(['user_id' => $user->id]);
        $otherStudent = new Student(['user_id' => $user->id + 1]);
        $policy = new StudentPolicy;

        $this->assertTrue($policy->view($user, $ownStudent));
        $this->assertTrue($policy->update($user, $ownStudent));
        $this->assertFalse($policy->view($user, $otherStudent));
        $this->assertFalse($policy->update($user, $otherStudent));
    }

    public function test_user_can_update_own_account_but_not_another_account(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $policy = new UserPolicy;

        $this->assertTrue($policy->update($user, $user));
        $this->assertFalse($policy->update($user, $otherUser));
    }

    public function test_administrator_with_explicit_permission_can_access_records_globally(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Área Pedagógica'));
        $user->givePermissionTo([
            Permission::findOrCreate('view Teacher'),
            Permission::findOrCreate('update Student'),
        ]);

        $this->assertTrue((new TeacherPolicy)->view($user, new Teacher(['id_user' => 999])));
        $this->assertTrue((new StudentPolicy)->update($user, new Student(['user_id' => 999])));
    }

    public function test_professor_schedule_access_is_limited_to_own_teacher(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Professor'));
        $user->givePermissionTo([
            Permission::findOrCreate('view Schedule'),
            Permission::findOrCreate('update Schedule'),
            Permission::findOrCreate('delete Schedule'),
            Permission::findOrCreate('delete-any Schedule'),
            Permission::findOrCreate('restore-any Schedule'),
            Permission::findOrCreate('force-delete-any Schedule'),
        ]);

        $teacher = new Teacher;
        $teacher->setAttribute('id', 42);
        $user->setRelation('teacher', $teacher);

        $ownSchedule = new Schedule(['id_teacher' => 42]);
        $otherSchedule = new Schedule(['id_teacher' => 43]);
        $policy = new SchedulePolicy;

        $this->assertTrue($policy->view($user, $ownSchedule));
        $this->assertTrue($policy->create($user));
        $this->assertTrue($policy->update($user, $ownSchedule));
        $this->assertFalse($policy->view($user, $otherSchedule));
        $this->assertFalse($policy->update($user, $otherSchedule));
        $this->assertFalse($policy->delete($user, $ownSchedule));
        $this->assertFalse($policy->deleteAny($user));
        $this->assertFalse($policy->restoreAny($user));
        $this->assertFalse($policy->forceDeleteAny($user));
    }

    public function test_professor_can_access_schedule_creation(): void
    {
        $user = User::factory()->create(['mfa_grace_until' => now()->addDay()]);
        $user->assignRole(Role::findOrCreate('Professor'));

        $this->actingAs($user);

        $this->assertTrue(ScheduleResource::canCreate());

        $this->get('/maestro/schedules/create?weekday=2&timeperiod=5')
            ->assertOk();
    }

    public function test_end_user_roles_cannot_turn_explicit_permissions_into_global_access(): void
    {
        $studentUser = User::factory()->create();
        $studentUser->assignRole(Role::findOrCreate('Aluno'));
        $studentUser->givePermissionTo([
            Permission::findOrCreate('view Schedule'),
            Permission::findOrCreate('view Teacher'),
            Permission::findOrCreate('delete-any Schedule'),
        ]);

        $professor = User::factory()->create();
        $professor->assignRole(Role::findOrCreate('Professor'));
        $professor->givePermissionTo([
            Permission::findOrCreate('update Student'),
            Permission::findOrCreate('delete-any Student'),
        ]);

        $this->assertFalse((new SchedulePolicy)->view($studentUser, new Schedule(['id_teacher' => 1])));
        $this->assertFalse((new SchedulePolicy)->deleteAny($studentUser));
        $this->assertFalse((new TeacherPolicy)->view($studentUser, new Teacher(['id_user' => 1])));
        $this->assertFalse((new StudentPolicy)->update($professor, new Student(['user_id' => 1])));
        $this->assertFalse((new StudentPolicy)->deleteAny($professor));
    }

    public function test_schedule_query_scopes_professors_but_not_super_admins(): void
    {
        SchoolYear::create([
            'schoolyear' => '2026/2027',
            'start_date_especializado' => '2026-09-01',
            'end_date_especializado' => '2027-08-31',
            'start_date_profissional' => '2026-09-01',
            'end_date_profissional' => '2027-08-31',
            'start_date_livre' => '2026-09-01',
            'end_date_livre' => '2027-08-31',
            'active' => true,
        ]);

        $professor = User::factory()->create();
        $professor->assignRole(Role::findOrCreate('Professor'));
        $teacher = Teacher::create([
            'number' => 1001,
            'name' => 'Professor Teste',
            'acronym' => 'PT',
            'birthdate' => '1980-01-01',
            'startingdate' => '2005-01-01',
            'id_user' => $professor->id,
        ]);

        $this->actingAs($professor);
        $professorQuery = ScheduleResource::getEloquentQuery();

        $this->assertStringContainsString('id_teacher', $professorQuery->toSql());
        $this->assertContains($teacher->id, $professorQuery->getBindings());

        $superAdmin = User::query()->where('email', 'admin@admin.pt')->firstOrFail();
        $this->actingAs($superAdmin);
        $superAdminQuery = ScheduleResource::getEloquentQuery();

        $this->assertStringNotContainsString('id_teacher', $superAdminQuery->toSql());

        $superAdmin->assignRole(Role::findOrCreate('Aluno'));
        $superAdminWithStudentRoleQuery = ScheduleResource::getEloquentQuery();

        $this->assertStringNotContainsString('0 = 1', $superAdminWithStudentRoleQuery->toSql());

        $studentUser = User::factory()->create();
        $studentUser->assignRole(Role::findOrCreate('Aluno'));
        $this->actingAs($studentUser);

        $this->assertStringContainsString('0 = 1', ScheduleResource::getEloquentQuery()->toSql());
    }

    public function test_export_is_denied_on_the_server_to_non_super_admins(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('Professor'));
        $this->actingAs($user);

        $this->expectException(AuthorizationException::class);

        ScheduleResource::exportSchedules();
    }

    public function test_only_super_admin_is_authorized_to_export_schedules(): void
    {
        $superAdmin = User::query()->where('email', 'admin@admin.pt')->firstOrFail();
        $professor = User::factory()->create();
        $professor->assignRole(Role::findOrCreate('Professor'));

        $this->assertTrue(Gate::forUser($superAdmin)->allows('export', Schedule::class));
        $this->assertFalse(Gate::forUser($professor)->allows('export', Schedule::class));
    }

    public function test_users_without_a_recognized_role_cannot_access_the_admin_panel(): void
    {
        $user = User::factory()->create();
        $panel = Panel::make()->id('admin');

        $this->assertFalse($user->canAccessPanel($panel));

        $user->assignRole(Role::findOrCreate('Professor'));

        $this->assertTrue($user->fresh()->canAccessPanel($panel));
    }
}
