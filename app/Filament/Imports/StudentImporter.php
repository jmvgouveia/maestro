<?php

namespace App\Filament\Imports;

use App\Models\Gender;
use App\Models\Student;
use App\Models\User;
use App\Services\UserActivationService;
use Carbon\Carbon;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentImporter extends Importer
{
    protected static ?string $model = Student::class;

    protected ?string $guardianName = null;

    protected ?string $guardianEmail = null;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('number')
                ->label('Número de Estudante')
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('name')
                ->label('Nome')
                ->rules(['required', 'string', 'max:255']),
            ImportColumn::make('birthdate')
                ->label('Data de Nascimento')
                ->rules(['required', 'date']),
            ImportColumn::make('id_gender')
                ->label('Género')
                ->rules(['required', 'integer', 'exists:genders,id']),
            ImportColumn::make('email')
                ->label('Email')
                ->rules(['nullable', 'email']),
            ImportColumn::make('guardian_name')
                ->label('Nome do Encarregado de Educação')
                ->rules(['nullable', 'string', 'max:255', 'required_with:guardian_email']),
            ImportColumn::make('guardian_email')
                ->label('Email do Encarregado de Educação')
                ->rules(['nullable', 'email', 'max:255', 'required_with:guardian_name']),
        ];
    }

    public function resolveRecord(): ?Student
    {
        return Student::firstOrNew([
            'number' => trim((string) ($this->data['number'] ?? '')),
        ]);
    }

    protected function beforeValidate(): void
    {
        $this->data['number'] = trim((string) ($this->data['number'] ?? ''));
        $this->data['name'] = trim((string) ($this->data['name'] ?? ''));

        $email = trim((string) ($this->data['email'] ?? ''));
        $this->data['email'] = $email === '' ? null : $email;

        $guardianName = trim((string) ($this->data['guardian_name'] ?? ''));
        $this->data['guardian_name'] = $guardianName === '' ? null : $guardianName;

        $guardianEmail = trim((string) ($this->data['guardian_email'] ?? ''));
        $this->data['guardian_email'] = $guardianEmail === '' ? null : strtolower($guardianEmail);

        $this->data['birthdate'] = $this->normalizeDate($this->data['birthdate'] ?? null);
        $this->data['id_gender'] = $this->normalizeGender($this->data['id_gender'] ?? null);
    }

    private function normalizeDate(mixed $value): string
    {
        $value = trim((string) $value);

        foreach (['d/m/Y', 'd-m-Y', 'd.m.Y', 'Y-m-d', 'd/m/Y H:i:s', 'Y-m-d H:i:s'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Throwable) {
                // Try the next supported format.
            }
        }

        throw ValidationException::withMessages([
            'birthdate' => 'A data de nascimento deve estar num formato válido.',
        ]);
    }

    private function normalizeGender(mixed $value): mixed
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        $normalized = Str::lower(Str::ascii(trim((string) $value)));
        $genderId = Gender::query()
            ->get(['id', 'gender'])
            ->first(function (Gender $gender) use ($normalized): bool {
                return Str::lower(Str::ascii($gender->gender)) === $normalized;
            })?->getKey();

        if ($genderId === null) {
            throw ValidationException::withMessages([
                'id_gender' => 'O género deve ser Masculino, Feminino, Outro ou um ID válido.',
            ]);
        }

        return $genderId;
    }

    protected function beforeFill(): void
    {
        $this->guardianName = $this->data['guardian_name'] ?? null;
        $this->guardianEmail = $this->data['guardian_email'] ?? null;
        unset($this->data['guardian_name'], $this->data['guardian_email']);
    }

    public function saveRecord(): void
    {
        parent::saveRecord();

        $this->processGuardian($this->record, $this->guardianName, $this->guardianEmail);
    }

    private function processGuardian(Student $student, ?string $name, ?string $email): void
    {
        if (blank($email)) {
            return;
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => str()->random(40),
                'is_active' => false,
            ]
        );

        if (! $user->hasRole(User::ROLE_GUARDIAN)) {
            $user->assignRole(User::ROLE_GUARDIAN);
        }

        $student->guardians()->syncWithoutDetaching([$user->id]);

        if ($user->wasRecentlyCreated) {
            app(UserActivationService::class)->issueAndNotify($user);
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $count = $import->successful_rows;

        return "{$count} Alunos Importados com sucesso.";
    }
}
