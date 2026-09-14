<?php

namespace Tests\Feature;

use App\Filament\Resources\RegistrationSubjectResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GuardianStudentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guardian_must_select_one_of_the_associated_students(): void
    {
        $guardian = $this->guardian();
        $firstStudent = $this->student('First Student', 1001);
        $secondStudent = $this->student('Second Student', 1002);

        $guardian->guardianStudents()->attach([$firstStudent, $secondStudent]);
        $this->actingAs($guardian);

        $this->assertNull($guardian->activeGuardianStudentId());
        $this->assertTrue($guardian->setActiveGuardianStudent($secondStudent));
        $this->assertSame($secondStudent, $guardian->activeGuardianStudentId());
        $this->assertFalse($guardian->setActiveGuardianStudent($firstStudent + 100));
        $this->assertSame($secondStudent, $guardian->activeGuardianStudentId());
    }

    public function test_selected_student_is_restored_when_selector_is_remounted(): void
    {
        $guardian = $this->guardian();
        $student = $this->student('Selected Student', 1001);
        $guardian->guardianStudents()->attach($student);
        $this->actingAs($guardian);

        Livewire::test(\App\Livewire\GuardianStudentSelector::class)
            ->call('updateActiveStudent', $student);

        Livewire::test(\App\Livewire\GuardianStudentSelector::class)
            ->assertSet('studentId', $student);
    }

    public function test_registration_subject_query_is_limited_to_active_student(): void
    {
        $guardian = $this->guardian();
        $firstStudent = $this->student('First Student', 1001);
        $secondStudent = $this->student('Second Student', 1002);
        $guardian->guardianStudents()->attach([$firstStudent, $secondStudent]);

        $schoolYear = DB::table('schoolyears')->insertGetId([
            'schoolyear' => '2026/2027',
            'start_date_profissional' => '2026-09-01',
            'end_date_profissional' => '2027-08-31',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $course = DB::table('courses')->insertGetId([
            'name' => 'Course',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $class = DB::table('classes')->insertGetId([
            'name' => 'Class',
            'id_course' => $course,
            'year' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $subject = DB::table('subjects')->insertGetId([
            'name' => 'Subject',
            'acronym' => 'SUB-TEST',
            'type' => 'Letiva',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $firstRegistration = DB::table('registrations')->insertGetId([
            'id_student' => $firstStudent,
            'id_course' => $course,
            'id_schoolyear' => $schoolYear,
            'id_class' => $class,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $secondRegistration = DB::table('registrations')->insertGetId([
            'id_student' => $secondStudent,
            'id_course' => $course,
            'id_schoolyear' => $schoolYear,
            'id_class' => $class,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $firstSubject = DB::table('registrations_subjects')->insertGetId([
            'id_registration' => $firstRegistration,
            'id_subject' => $subject,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $secondSubject = DB::table('registrations_subjects')->insertGetId([
            'id_registration' => $secondRegistration,
            'id_subject' => $subject,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($guardian);
        $guardian->setActiveGuardianStudent($secondStudent);

        $this->assertSame([$secondSubject], RegistrationSubjectResource::getEloquentQuery()->pluck('id')->all());
        $this->assertNotContains($firstSubject, RegistrationSubjectResource::getEloquentQuery()->pluck('id')->all());
    }

    private function guardian(): User
    {
        $role = Role::findOrCreate(User::ROLE_GUARDIAN, 'web');
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function student(string $name, int $number): int
    {
        return DB::table('students')->insertGetId([
            'number' => $number,
            'name' => $name,
            'birthdate' => '2010-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
