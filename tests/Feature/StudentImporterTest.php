<?php

namespace Tests\Feature;

use App\Filament\Imports\StudentImporter;
use App\Models\Gender;
use App\Models\Student;
use App\Models\User;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_importer_normalizes_student_data(): void
    {
        $user = User::factory()->create();
        $import = Import::create([
            'file_name' => 'students.csv',
            'file_path' => 'students.csv',
            'importer' => StudentImporter::class,
            'total_rows' => 2,
            'user_id' => $user->getKey(),
        ]);
        $importer = new StudentImporter($import, [
            'number' => 'number',
            'name' => 'name',
            'birthdate' => 'birthdate',
            'id_gender' => 'id_gender',
            'email' => 'email',
        ], []);

        $importer([
            'number' => '1001',
            'name' => 'Aluno Masculino',
            'birthdate' => '15/01/2005',
            'id_gender' => 'Masculino',
            'email' => '   ',
        ]);
        $importer([
            'number' => '1002',
            'name' => 'Aluna Feminina',
            'birthdate' => '2006-02-20',
            'id_gender' => 'Feminino',
            'email' => 'aluna@example.test',
        ]);

        $this->assertDatabaseHas('students', [
            'number' => '1001',
            'birthdate' => '2005-01-15',
            'id_gender' => Gender::where('gender', 'Masculino')->value('id'),
            'email' => null,
        ]);
        $this->assertDatabaseHas('students', [
            'number' => '1002',
            'birthdate' => '2006-02-20',
            'id_gender' => Gender::where('gender', 'Feminino')->value('id'),
            'email' => 'aluna@example.test',
        ]);

        $this->assertSame(2, Student::count());
    }

    public function test_importer_updates_student_with_existing_number(): void
    {
        $user = User::factory()->create();
        $gender = Gender::where('gender', 'Masculino')->firstOrFail();
        Student::create([
            'number' => '1001',
            'name' => 'Nome antigo',
            'birthdate' => '2005-01-15',
            'id_gender' => $gender->id,
        ]);
        $import = Import::create([
            'file_name' => 'students.csv',
            'file_path' => 'students.csv',
            'importer' => StudentImporter::class,
            'total_rows' => 1,
            'user_id' => $user->getKey(),
        ]);
        $importer = new StudentImporter($import, [
            'number' => 'number',
            'name' => 'name',
            'birthdate' => 'birthdate',
            'id_gender' => 'id_gender',
            'email' => 'email',
        ], []);

        $importer([
            'number' => '1001',
            'name' => 'Nome atualizado',
            'birthdate' => '2005-01-15',
            'id_gender' => 'Masculino',
            'email' => '',
        ]);

        $this->assertSame(1, Student::count());
        $this->assertDatabaseHas('students', [
            'number' => '1001',
            'name' => 'Nome atualizado',
        ]);
    }
}
