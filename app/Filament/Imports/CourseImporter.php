<?php

namespace App\Filament\Imports;

use App\Models\Course;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CourseImporter extends Importer
{
    protected static ?string $model = Course::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label('Nome do Curso')
                ->rules([
                    'required',
                    'string',
                    'max:255',
                    'min:3',
                    Rule::unique(Course::class, 'name'),
                ])
                ->example('Licenciatura em Música'),
            ImportColumn::make('type')
                ->label('Tipologia')
                ->rules(['required', 'string', Rule::in(array_keys(Course::types()))])
                ->example('Profissional'),
        ];
    }

    public function resolveRecord(): ?Course
    {
        return DB::transaction(function () {
            return new Course;
        });
    }

    protected function beforeFill(): void
    {
        $this->data['name'] = trim($this->data['name'] ?? '');
        $this->data['type'] = trim($this->data['type'] ?? '');
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $successful = $import->successful_rows;
        $failed = $import->failed_rows;
        $total = $import->total_rows;

        if ($successful === 0) {
            return "Nenhum curso foi importado. {$failed} registos falharam de {$total} processados.";
        }

        $message = "Importação concluída: {$successful} cursos importados com sucesso";

        if ($failed > 0) {
            $message .= ", {$failed} falharam";
        }

        $message .= " de {$total} registos processados.";

        return $message;
    }
}
