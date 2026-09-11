<?php

namespace App\Filament\Imports;

use App\Models\Registration;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SchoolYear;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\DB;
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
                ->rules(['required', 'string'])
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
        $this->data['id_course']     = (int) ($this->data['id_course']     ?? 0);
        $this->data['id_schoolyear'] = SchoolYear::query()->where('active', true)->value('id');
        $this->data['id_class']      = (int) ($this->data['id_class']      ?? 0);

        // Não tocamos em id_subject aqui (fica apenas no originalData; sem estado partilhado)
        unset($this->data['id_subject']);
    }

    public function resolveRecord(): ?Registration
    {
        $this->data['id_student'] = $this->resolveStudentId();

        return DB::transaction(function () {
            // 1) Garantir/obter a registration (mesma chave composta = mesmo registo)
            $attrs = [
                'id_student'    => $this->data['id_student'],
                'id_course'     => $this->data['id_course'],
                'id_schoolyear' => $this->data['id_schoolyear'],
                'id_class'      => $this->data['id_class'],
            ];

            $registration = Registration::firstOrCreate($attrs);

            // 2) Extrair e validar a disciplina desta linha (SEM guardar em propriedade)
            $rawSubject = $this->extractRawSubjectFromOriginalData();
            $subjectId  = $this->normalizeSubjectId($rawSubject);

            if ($subjectId !== null && Subject::whereKey($subjectId)->exists()) {
                // 3) Anexar 1 disciplina por linha, sem duplicar
                $registration->subjects()->syncWithoutDetaching([$subjectId]);

                Log::debug('Disciplina anexada', [
                    'registration_id' => $registration->id,
                    'id_subject'      => $subjectId,
                ]);
            } else {
                Log::warning('Disciplina ausente/inválida (linha não falha)', [
                    'raw'             => $rawSubject,
                    'registration_id' => $registration->id,
                    'row'             => $this->originalData,
                ]);
            }

            return $registration;
        });
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

    /**
     * Lê o campo da disciplina a partir do CSV original, aceitando vários cabeçalhos.
     */
    private function extractRawSubjectFromOriginalData(): ?string
    {
        $candidatos = [
            'id_subject',
            'IDs das Disciplinas',
            'ID_disciplina',
            'ID da Disciplina',
            'ID da disciplina',
            'Disciplina',
            'IDs_das_Disciplinas',
        ];

        foreach ($candidatos as $key) {
            if (array_key_exists($key, $this->originalData)) {
                $val = (string) $this->originalData[$key];
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
        $ok  = $import->successful_rows;
        $ko  = $import->failed_rows;
        $tot = $import->total_rows;

        if ($ok === 0) {
            return "Nenhuma linha foi importada. {$ko} falharam de {$tot} processadas.";
        }

        $msg = "Importação concluída: {$ok} linhas importadas com sucesso";
        if ($ko > 0) {
            $msg .= ", {$ko} falharam";
        }
        return $msg . " de {$tot} processadas.";
    }
}
