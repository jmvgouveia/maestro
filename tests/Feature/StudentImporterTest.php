<?php

namespace Tests\Feature;

use App\Filament\Imports\GuardianConversionImporter;
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
        $this->assertDatabaseMissing('users', ['email' => 'aluna@example.test']);

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

    public function test_importer_creates_and_reuses_guardian_accounts(): void
    {
        $existingGuardian = User::factory()->create([
            'name' => 'Nome original',
            'email' => 'ee@example.test',
            'is_active' => true,
        ]);
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
            'guardian_name' => 'guardian_name',
            'guardian_email' => 'guardian_email',
        ], []);

        $importer([
            'number' => '1003',
            'name' => 'Primeiro filho',
            'birthdate' => '2007-03-10',
            'id_gender' => 'Masculino',
            'email' => '',
            'guardian_name' => 'Nome importado',
            'guardian_email' => 'ee@example.test',
        ]);
        $importer([
            'number' => '1004',
            'name' => 'Segundo filho',
            'birthdate' => '2008-04-11',
            'id_gender' => 'Feminino',
            'email' => '',
            'guardian_name' => 'Novo EE',
            'guardian_email' => 'novo-ee@example.test',
        ]);

        $this->assertSame('Nome original', $existingGuardian->refresh()->name);
        $this->assertTrue($existingGuardian->hasRole(User::ROLE_GUARDIAN));
        $this->assertDatabaseHas('users', [
            'email' => 'novo-ee@example.test',
            'name' => 'Novo EE',
        ]);
        $this->assertDatabaseCount('student_guardians', 2);
        $this->assertDatabaseCount('students', 2);
    }

    public function test_guardian_conversion_updates_existing_student_account(): void
    {
        $studentUser = User::factory()->create([
            'name' => 'Nome do aluno',
            'email' => 'ee@example.test',
        ]);
        $studentUser->assignRole('Aluno');
        $student = Student::create([
            'number' => '2001',
            'name' => 'Nome do filho',
            'birthdate' => '2009-01-01',
            'id_gender' => Gender::where('gender', 'Masculino')->value('id'),
            'user_id' => $studentUser->getKey(),
        ]);
        $import = Import::create([
            'file_name' => 'guardian-conversion.csv',
            'file_path' => 'guardian-conversion.csv',
            'importer' => GuardianConversionImporter::class,
            'total_rows' => 1,
            'user_id' => $studentUser->getKey(),
        ]);
        $importer = new GuardianConversionImporter($import, [
            'number' => 'num_aluno',
            'guardian_name' => 'Nome EE',
            'guardian_email' => 'Email EE',
        ], []);

        $importer([
            'num_aluno' => '2001',
            'Nome EE' => 'Maria Silva',
            'Email EE' => 'ee@example.test',
        ]);

        $this->assertSame('Maria Silva', $studentUser->refresh()->name);
        $this->assertTrue($studentUser->hasRole(User::ROLE_GUARDIAN));
        $this->assertFalse($studentUser->hasRole('Aluno'));
        $this->assertNull($student->refresh()->user_id);
        $this->assertDatabaseHas('student_guardians', [
            'student_id' => $student->getKey(),
            'user_id' => $studentUser->getKey(),
        ]);
    }
}
