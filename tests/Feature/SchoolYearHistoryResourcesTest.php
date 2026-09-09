<?php

namespace Tests\Feature;

use App\Filament\Imports\CourseSubjectImporter;
use App\Filament\Resources\CourseSubjectResource;
use App\Filament\Resources\CourseSubjectResource\Pages\ListCourseSubjects;
use App\Filament\Resources\RegistrationResource\Pages\ListRegistrations;
use App\Filament\Resources\TeacherSubjectResource;
use App\Filament\Resources\TeacherSubjectResource\Pages\ListTeacherSubjects;
use App\Models\CourseSubject;
use App\Models\Registration;
use App\Models\SchoolYear;
use App\Models\TeacherSubject;
use App\Models\User;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchoolYearHistoryResourcesTest extends TestCase
{
    use RefreshDatabase;

    private int $activeYearId;

    private int $historicalYearId;

    private int $activeCourseSubjectId;

    private int $historicalCourseSubjectId;

    private int $activeRegistrationId;

    private int $historicalRegistrationId;

    private int $teacherId;

    private int $otherTeacherId;

    private int $activeTeacherSubjectId;

    private int $historicalTeacherSubjectId;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->createFixtures();
    }

    public function test_secretaria_has_panel_access_and_permissions_for_the_three_resources(): void
    {
        $user = User::factory()->create();
        $role = Role::findByName('Secretaria');
        $user->assignRole($role);

        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));

        foreach (['Registration', 'TeacherSubject', 'CourseSubject'] as $model) {
            foreach (['view', 'view-any', 'create', 'update', 'delete', 'delete-any'] as $ability) {
                $this->assertTrue($role->hasPermissionTo("{$ability} {$model}"));
            }
        }
    }

    public function test_secretaria_sees_active_year_by_default_and_can_select_history(): void
    {
        $this->actingAs($this->createSecretaria());

        Livewire::test(ListCourseSubjects::class)
            ->assertCanSeeTableRecords([CourseSubject::findOrFail($this->activeCourseSubjectId)])
            ->assertCanNotSeeTableRecords([CourseSubject::findOrFail($this->historicalCourseSubjectId)])
            ->filterTable('id_schoolyear', $this->historicalYearId)
            ->assertCanSeeTableRecords([CourseSubject::findOrFail($this->historicalCourseSubjectId)])
            ->assertCanNotSeeTableRecords([CourseSubject::findOrFail($this->activeCourseSubjectId)])
            ->assertSee('Modo de consulta')
            ->assertActionHidden('create')
            ->assertTableActionHidden('edit', $this->historicalCourseSubjectId)
            ->assertTableActionHidden('import')
            ->assertTableBulkActionHidden('delete');
    }

    public function test_secretaria_can_filter_all_three_resources_by_historical_year(): void
    {
        $this->actingAs($this->createSecretaria());

        Livewire::test(ListRegistrations::class)
            ->filterTable('id_schoolyear', $this->historicalYearId)
            ->assertCanSeeTableRecords([Registration::findOrFail($this->historicalRegistrationId)])
            ->assertCanNotSeeTableRecords([Registration::findOrFail($this->activeRegistrationId)]);

        Livewire::test(ListTeacherSubjects::class)
            ->filterTable('id_schoolyear', $this->historicalYearId)
            ->assertCanSeeTableRecords([TeacherSubject::findOrFail($this->historicalTeacherSubjectId)])
            ->assertCanNotSeeTableRecords([TeacherSubject::findOrFail($this->activeTeacherSubjectId)]);
    }

    public function test_professor_remains_scoped_to_own_subjects_in_active_year(): void
    {
        $professor = User::factory()->create();
        $professor->assignRole(Role::findByName('Professor'));
        DB::table('teachers')->where('id', $this->teacherId)->update(['id_user' => $professor->getKey()]);

        $otherActiveId = DB::table('teacher_subjects')->insertGetId([
            'id_teacher' => $this->otherTeacherId,
            'id_subject' => DB::table('subjects')->value('id'),
            'id_schoolyear' => $this->activeYearId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($professor);

        $ids = TeacherSubjectResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($ids->contains($this->activeTeacherSubjectId));
        $this->assertFalse($ids->contains($this->historicalTeacherSubjectId));
        $this->assertFalse($ids->contains($otherActiveId));
        $this->assertFalse(TeacherSubjectResource::canBrowseSchoolYearHistory());
    }

    public function test_historical_records_cannot_be_edited_or_deleted_even_by_super_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::findByName('Super Admin'));
        $this->actingAs($admin);

        $historical = CourseSubject::findOrFail($this->historicalCourseSubjectId);
        $active = CourseSubject::findOrFail($this->activeCourseSubjectId);

        $this->assertFalse(CourseSubjectResource::canEdit($historical));
        $this->assertFalse(CourseSubjectResource::canDelete($historical));
        $this->assertFalse(Gate::forUser($admin)->allows('update', $historical));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $historical));
        $this->assertTrue(CourseSubjectResource::canEdit($active));
        $this->assertTrue(CourseSubjectResource::canDelete($active));
    }

    public function test_model_rejects_creating_or_deleting_historical_records(): void
    {
        $historical = CourseSubject::findOrFail($this->historicalCourseSubjectId);

        try {
            $historical->delete();
            $this->fail('A eliminação histórica deveria ter sido bloqueada.');
        } catch (AuthorizationException) {
            $this->assertDatabaseHas('course_subjects', ['id' => $historical->getKey()]);
        }

        $this->expectException(AuthorizationException::class);

        CourseSubject::create([
            'id_course' => $historical->id_course,
            'id_subject' => $historical->id_subject,
            'id_schoolyear' => $this->historicalYearId,
        ]);
    }

    public function test_importer_forces_the_active_school_year(): void
    {
        $user = $this->createSecretaria();
        $this->actingAs($user);

        $import = Import::create([
            'file_name' => 'course-subjects.csv',
            'file_path' => 'course-subjects.csv',
            'importer' => CourseSubjectImporter::class,
            'total_rows' => 1,
            'user_id' => $user->getKey(),
        ]);
        $historical = CourseSubject::findOrFail($this->historicalCourseSubjectId);
        $activeMatchesBefore = DB::table('course_subjects')
            ->where('id_course', $historical->id_course)
            ->where('id_subject', $historical->id_subject)
            ->where('id_schoolyear', $this->activeYearId)
            ->count();

        $importer = new CourseSubjectImporter($import, [
            'id_course' => 'id_course',
            'id_subject' => 'id_subject',
            'id_schoolyear' => 'id_schoolyear',
        ], []);

        $importer([
            'id_course' => $historical->id_course,
            'id_subject' => $historical->id_subject,
            'id_schoolyear' => $this->historicalYearId,
        ]);

        $this->assertSame(
            $activeMatchesBefore + 1,
            DB::table('course_subjects')
                ->where('id_course', $historical->id_course)
                ->where('id_subject', $historical->id_subject)
                ->where('id_schoolyear', $this->activeYearId)
                ->count(),
        );
    }

    public function test_historical_registration_subjects_cannot_be_changed(): void
    {
        $historical = Registration::findOrFail($this->historicalRegistrationId);
        $subjectId = DB::table('subjects')->value('id');

        $this->expectException(AuthorizationException::class);

        $historical->subjects()->syncWithoutDetaching([$subjectId]);
    }

    public function test_activating_a_school_year_deactivates_the_previous_one(): void
    {
        $newActive = SchoolYear::createWithExclusiveActive([
            'schoolyear' => '2027/2028',
            'start_date_especializado' => '2027-09-01',
            'end_date_especializado' => '2028-08-31',
            'start_date_profissional' => '2027-09-01',
            'end_date_profissional' => '2028-08-31',
            'start_date_livre' => '2027-09-01',
            'end_date_livre' => '2028-08-31',
            'active' => true,
        ]);

        $this->assertTrue((bool) $newActive->fresh()->active);
        $this->assertFalse((bool) SchoolYear::findOrFail($this->activeYearId)->active);
        $this->assertSame(1, SchoolYear::query()->where('active', true)->count());
    }

    private function createSecretaria(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('Secretaria'));

        return $user;
    }

    private function createFixtures(): void
    {
        $now = now();
        $this->historicalYearId = DB::table('schoolyears')->insertGetId([
            'schoolyear' => '2025/2026',
            'start_date_especializado' => '2025-09-01',
            'end_date_especializado' => '2026-08-31',
            'start_date_profissional' => '2025-09-01',
            'end_date_profissional' => '2026-08-31',
            'start_date_livre' => '2025-09-01',
            'end_date_livre' => '2026-08-31',
            'active' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->activeYearId = DB::table('schoolyears')->insertGetId([
            'schoolyear' => '2026/2027',
            'start_date_especializado' => '2026-09-01',
            'end_date_especializado' => '2027-08-31',
            'start_date_profissional' => '2026-09-01',
            'end_date_profissional' => '2027-08-31',
            'start_date_livre' => '2026-09-01',
            'end_date_livre' => '2027-08-31',
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $courseId = DB::table('courses')->insertGetId(['name' => 'Música', 'created_at' => $now, 'updated_at' => $now]);
        $subjectId = DB::table('subjects')->insertGetId([
            'name' => 'Piano',
            'acronym' => 'PNO',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $classId = DB::table('classes')->insertGetId([
            'name' => 'A',
            'id_course' => $courseId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $studentId = DB::table('students')->insertGetId([
            'number' => 1001,
            'name' => 'Aluno',
            'birthdate' => '2010-01-01',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->teacherId = DB::table('teachers')->insertGetId([
            'number' => 2001,
            'name' => 'Professor Um',
            'acronym' => 'P1',
            'birthdate' => '1980-01-01',
            'startingdate' => '2020-01-01',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->otherTeacherId = DB::table('teachers')->insertGetId([
            'number' => 2002,
            'name' => 'Professor Dois',
            'acronym' => 'P2',
            'birthdate' => '1981-01-01',
            'startingdate' => '2021-01-01',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->activeCourseSubjectId = DB::table('course_subjects')->insertGetId([
            'id_course' => $courseId,
            'id_subject' => $subjectId,
            'id_schoolyear' => $this->activeYearId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->historicalCourseSubjectId = DB::table('course_subjects')->insertGetId([
            'id_course' => $courseId,
            'id_subject' => $subjectId,
            'id_schoolyear' => $this->historicalYearId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->activeRegistrationId = DB::table('registrations')->insertGetId([
            'id_student' => $studentId,
            'id_course' => $courseId,
            'id_schoolyear' => $this->activeYearId,
            'id_class' => $classId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->historicalRegistrationId = DB::table('registrations')->insertGetId([
            'id_student' => $studentId,
            'id_course' => $courseId,
            'id_schoolyear' => $this->historicalYearId,
            'id_class' => $classId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->activeTeacherSubjectId = DB::table('teacher_subjects')->insertGetId([
            'id_teacher' => $this->teacherId,
            'id_subject' => $subjectId,
            'id_schoolyear' => $this->activeYearId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->historicalTeacherSubjectId = DB::table('teacher_subjects')->insertGetId([
            'id_teacher' => $this->teacherId,
            'id_subject' => $subjectId,
            'id_schoolyear' => $this->historicalYearId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
