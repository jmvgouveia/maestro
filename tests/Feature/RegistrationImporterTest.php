<?php

namespace Tests\Feature;

use App\Filament\Imports\RegistrationImporter;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Registration;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_importer_accepts_the_registration_csv_headers_and_attaches_subject(): void
    {
        $user = User::factory()->create();
        $schoolYear = SchoolYear::create(['schoolyear' => '2026', 'active' => true]);
        $student = Student::create([
            'number' => '15011',
            'name' => 'Aluno de teste',
            'birthdate' => '2005-01-01',
        ]);
        $course = Course::create(['name' => 'Curso de teste']);
        $class = Classes::create(['name' => 'Turma de teste', 'id_course' => $course->id]);
        $subject = Subject::create(['name' => 'Disciplina de teste', 'acronym' => 'DT']);
        $import = Import::create([
            'file_name' => 'matriculas.csv',
            'file_path' => 'matriculas.csv',
            'importer' => RegistrationImporter::class,
            'total_rows' => 1,
            'user_id' => $user->id,
        ]);
        $importer = new RegistrationImporter($import, [
            'id_student' => 'Nº Interno',
            'id_course' => 'Id_Curso',
            'id_schoolyear' => 'id_schoolyear',
            'id_class' => 'Id_Class',
            'id_subject' => 'Id_Subject',
        ], []);

        $importer([
            'Nº Interno' => $student->number,
            'Id_Curso' => (string) $course->id,
            'id_schoolyear' => (string) $schoolYear->id,
            'Id_Class' => (string) $class->id,
            'Id_Subject' => (string) $subject->id,
        ]);

        $registration = Registration::firstOrFail();
        $this->assertSame($schoolYear->id, $registration->id_schoolyear);
        $this->assertTrue($registration->subjects->contains($subject));
    }
}
