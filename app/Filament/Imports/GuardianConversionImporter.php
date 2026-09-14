<?php

namespace App\Filament\Imports;

use App\Models\Student;
use App\Models\User;
use App\Services\UserActivationService;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GuardianConversionImporter extends Importer
{
    protected static ?string $model = Student::class;

    protected ?string $guardianName = null;

    protected ?string $guardianEmail = null;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('number')
                ->label('Número do aluno')
                ->guess(['num_aluno', 'número do aluno'])
                ->rules(['required', 'string', 'max:255'])
                ->example('1001'),
            ImportColumn::make('guardian_name')
                ->label('Nome do Encarregado de Educação')
                ->guess(['nome ee', 'nome do ee', 'guardian_name'])
                ->rules(['required', 'string', 'max:255'])
                ->example('Maria Silva'),
            ImportColumn::make('guardian_email')
                ->label('Email do Encarregado de Educação')
                ->guess(['email ee', 'email do ee', 'guardian_email'])
                ->rules(['required', 'email', 'max:255'])
                ->example('maria.silva@example.com'),
        ];
    }

    public function resolveRecord(): ?Student
    {
        $student = Student::query()
            ->where('number', trim((string) ($this->data['number'] ?? '')))
            ->first();

        if (! $student) {
            throw ValidationException::withMessages([
                'number' => 'Não foi encontrado nenhum aluno com este número.',
            ]);
        }

        return $student;
    }

    protected function beforeValidate(): void
    {
        $this->data['number'] = trim((string) ($this->data['number'] ?? ''));
        $this->data['guardian_name'] = trim((string) ($this->data['guardian_name'] ?? ''));
        $this->data['guardian_email'] = strtolower(trim((string) ($this->data['guardian_email'] ?? '')));
    }

    protected function beforeFill(): void
    {
        $this->guardianName = $this->data['guardian_name'] ?? null;
        $this->guardianEmail = $this->data['guardian_email'] ?? null;
        unset($this->data['guardian_name'], $this->data['guardian_email']);
    }

    public function saveRecord(): void
    {
        DB::transaction(function (): void {
            parent::saveRecord();

            $email = $this->guardianEmail;
            $name = $this->guardianName;
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => str()->random(40),
                    'is_active' => false,
                ],
            );

            $user->forceFill(['name' => $name])->save();

            if (! $user->hasRole(User::ROLE_GUARDIAN)) {
                $user->assignRole(User::ROLE_GUARDIAN);
            }

            $studentUser = $this->record->user;

            if ($studentUser) {
                $this->record->guardians()->detach($studentUser->getKey());
                $this->record->forceFill(['user_id' => null])->save();

                if (! Student::where('user_id', $studentUser->getKey())->exists()) {
                    $studentUser->removeRole('Aluno');
                }
            }

            $this->record->guardians()->syncWithoutDetaching([$user->getKey()]);

            if ($user->wasRecentlyCreated) {
                app(UserActivationService::class)->issueAndNotify($user);
            }
        });
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return "{$import->successful_rows} conversões de encarregados concluídas.";
    }
}
