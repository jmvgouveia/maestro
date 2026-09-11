<?php

namespace App\Filament\Imports;

use App\Models\Classes;
use App\Models\Course;
use App\Models\Registration;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Subject;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class RegistrationImporter extends Importer
{
    protected static ?string $model = Registration::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('id_student')
                ->label('ID do ALUNO')
                // O número pode ser numérico ou alfanumérico (ex.: P5237).
                // A referência é convertida para o ID interno antes da validação.
                ->rules(['required'])
                ->example('6926'),

            ImportColumn::make('id_course')
                ->label('ID do Curso')
                ->rules(['required', 'integer', 'exists:courses,id'])
                ->example('1'),

            ImportColumn::make('id_schoolyear')
                ->label('ID do Ano Letivo')
                ->rules(['nullable', 'integer', 'exists:schoolyears,id'])
                ->example('1'),

            ImportColumn::make('id_class')
                ->label('ID da Turma')
                ->rules(['required', 'integer', 'exists:classes,id'])
                ->example('298'),

            // 1 disciplina por linha (nome do cabeçalho EXACTO no CSV)
            ImportColumn::make('id_subject')
                ->label('IDs das Disciplinas')
                // Não obrigamos a exists aqui; validamos manualmente para não falhar a linha
                ->rules(['nullable', 'string'])
                ->example('3')
                ->fillRecordUsing(null), // impede gravação direta no modelo principal
        ];
    }

    protected function beforeFill(): void
    {
        // Não tocamos em id_subject aqui (fica apenas no originalData; sem estado partilhado)
        unset($this->data['id_subject']);
    }

    public function resolveRecord(): ?Registration
    {
        $this->data['id_student'] = $this->resolveStudentId();
        $this->data['id_course'] = $this->resolveForeignKey(
            $this->data['id_course'] ?? null,
            Course::class,
            'id_course',
        );
        $this->data['id_schoolyear'] = SchoolYear::query()->where('active', true)->value('id');
        $this->data['id_class'] = $this->resolveForeignKey(
            $this->data['id_class'] ?? null,
            Classes::class,
            'id_class',
        );

        // A gravação só deve acontecer depois da validação do Importer.
        return Registration::firstOrNew([
            'id_student' => $this->data['id_student'],
            'id_course' => $this->data['id_course'],
            'id_schoolyear' => $this->data['id_schoolyear'],
            'id_class' => $this->data['id_class'],
        ]);
    }

    protected function afterSave(): void
    {
        $rawSubject = $this->extractRawSubjectFromOriginalData();
        $subjectId = $this->normalizeSubjectId($rawSubject);

        if ($subjectId !== null && Subject::whereKey($subjectId)->exists()) {
            $this->record->subjects()->syncWithoutDetaching([$subjectId]);

            Log::debug('Disciplina anexada', [
                'registration_id' => $this->record->id,
                'id_subject' => $subjectId,
            ]);

            return;
        }

        Log::warning('Disciplina ausente/inválida (linha não falha)', [
            'raw' => $rawSubject,
            'registration_id' => $this->record->id,
            'row' => $this->originalData,
        ]);
    }

    private function resolveStudentId(): int
    {
        $studentNumber = trim((string) ($this->data['id_student'] ?? ''));
        $studentId = Student::query()->where('number', $studentNumber)->value('id');

        if ($studentId === null) {
            throw ValidationException::withMessages([
                'id_student' => "Não foi encontrado nenhum aluno com o número {$studentNumber}.",
            ]);
        }

        return (int) $studentId;
    }

    private function resolveForeignKey(mixed $value, string $model, string $field): int
    {
        $value = trim((string) $value);

        if (! ctype_digit($value) || ! $model::query()->whereKey((int) $value)->exists()) {
            throw ValidationException::withMessages([
                $field => "O valor '{$value}' não corresponde a um registo válido.",
            ]);
        }

        return (int) $value;
    }

    /**
     * Lê o campo da disciplina a partir do CSV original, aceitando vários cabeçalhos.
     */
    private function extractRawSubjectFromOriginalData(): ?string
    {
        $candidatos = [
            'id_subject',
            'Id_Subject',
            'IDs das Disciplinas',
            'ID_disciplina',
            'ID da Disciplina',
            'ID da disciplina',
            'Disciplina',
            'IDs_das_Disciplinas',
        ];

        $originalData = collect($this->originalData)->keyBy(
            fn (mixed $value, mixed $key): string => mb_strtolower(trim((string) $key)),
        );

        foreach ($candidatos as $key) {
            $normalizedKey = mb_strtolower(trim($key));

            if ($originalData->has($normalizedKey)) {
                $val = (string) $originalData->get($normalizedKey);
                $val = trim($val, " \t\n\r\0\x0B\xEF\xBB\xBF"); // remove espaços e possível BOM
                if ($val !== '') {
                    return $val;
                }
            }
        }

        // fallback: se o Filament tiver mapeado para o "name" id_subject
        if (isset($this->data['id_subject'])) {
            return (string) $this->data['id_subject'];
        }

        return null;
    }

    /**
     * Extrai o PRIMEIRO número inteiro da string (ex.: "1", "1 abc", "  003").
     * Devolve null se não encontrar nenhum dígito.
     */
    private function normalizeSubjectId(?string $raw): ?int
    {
        if ($raw === null) {
            return null;
        }

        if (preg_match('/\d+/', $raw, $m)) {
            $id = (int) $m[0];

            return $id > 0 ? $id : null;
        }

        return null;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $ok = $import->successful_rows;
        $ko = $import->failed_rows;
        $tot = $import->total_rows;

        if ($ok === 0) {
            return "Nenhuma linha foi importada. {$ko} falharam de {$tot} processadas.";
        }

        $msg = "Importação concluída: {$ok} linhas importadas com sucesso";
        if ($ko > 0) {
            $msg .= ", {$ko} falharam";
        }

        return $msg." de {$tot} processadas.";
    }
}
