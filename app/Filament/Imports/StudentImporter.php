<?php

namespace App\Filament\Imports;

use App\Models\Gender;
use App\Models\Student;
use Carbon\Carbon;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentImporter extends Importer
{
    protected static ?string $model = Student::class;

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

    public static function getCompletedNotificationBody(Import $import): string
    {
        $count = $import->successful_rows;

        return "{$count} Alunos Importados com sucesso.";
    }
}
