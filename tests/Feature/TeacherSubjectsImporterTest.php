<?php

namespace Tests\Feature;

use App\Filament\Imports\TeacherSubjectsImporter;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherSubject;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherSubjectsImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_importer_recognizes_existing_teacher_subject_headers(): void
    {
        $columns = collect(TeacherSubjectsImporter::getColumns())
            ->mapWithKeys(fn ($column): array => [$column->getName() => $column]);

        $this->assertContains('docente', $columns['id_teacher']->getGuesses());
        $this->assertContains('disciplina', $columns['id_subject']->getGuesses());
        $this->assertContains('anoletivo', $columns['id_schoolyear']->getGuesses());
    }

    public function test_importer_resolves_teacher_number_and_plural_subject_header(): void
    {
        $user = \App\Models\User::factory()->create();
        $schoolYear = SchoolYear::create([
            'schoolyear' => '2025/2026',
            'start_date_especializado' => '2025-09-01',
            'end_date_especializado' => '2026-08-31',
            'start_date_profissional' => '2025-09-01',
            'end_date_profissional' => '2026-08-31',
            'start_date_livre' => '2025-09-01',
            'end_date_livre' => '2026-08-31',
            'active' => true,
        ]);
        $teacher = Teacher::create([
            'number' => 188,
            'name' => 'Professor Teste',
            'acronym' => 'PT188',
            'birthdate' => '1980-01-01',
            'startingdate' => '2020-01-01',
        ]);
        $subject = Subject::create(['name' => 'Disciplina Teste', 'acronym' => 'DT']);
        $import = Import::create([
            'file_name' => 'teacher-subjects.csv',
            'file_path' => 'teacher-subjects.csv',
            'importer' => TeacherSubjectsImporter::class,
            'total_rows' => 1,
            'user_id' => $user->getKey(),
        ]);

        (new TeacherSubjectsImporter($import, [
            'id_teacher' => 'id_teacher',
            'id_subject' => 'id_subjects',
            'id_schoolyear' => 'id_schoolyear',
        ], []))([
            'id_teacher' => (string) $teacher->number,
            'id_subjects' => (string) $subject->getKey(),
            'id_schoolyear' => (string) $schoolYear->getKey(),
        ]);

        $this->assertDatabaseHas('teacher_subjects', [
            'id_teacher' => $teacher->getKey(),
            'id_subject' => $subject->getKey(),
            'id_schoolyear' => $schoolYear->getKey(),
        ]);
    }
}
