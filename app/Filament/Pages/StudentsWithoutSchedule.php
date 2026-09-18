<?php

namespace App\Filament\Pages;

use App\Models\RegistrationSubject;
use App\Models\SchoolYear;
use App\Models\Subject;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentsWithoutSchedule extends Page
{
    use WithPagination;

    protected static string $view = 'filament.pages.students-without-schedule';

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'Auditoria';

    protected static ?string $navigationLabel = 'Alunos sem turno';

    protected static ?string $title = 'Alunos sem turno atribuído';

    protected static ?string $slug = 'auditoria-alunos-sem-turno';

    public string $search = '';

    public ?int $subjectFilterId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSubjectFilterId(): void
    {
        $this->resetPage();
    }

    public function subjectOptions(): array
    {
        return Subject::query()
            ->where('student_can_enroll', true)
            ->where('status', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function getRowsProperty(): LengthAwarePaginator
    {
        return $this->rowsQuery()->paginate(25);
    }

    public function exportRows(): StreamedResponse
    {
        $rows = $this->rowsQuery()->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Número', 'Aluno', 'Turma', 'Disciplina', 'E-mail do aluno'], ';');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->registration?->student?->number,
                    $row->registration?->student?->name,
                    $row->registration?->class?->name,
                    $row->subject?->name,
                    $row->registration?->student?->email ?? '—',
                ], ';');
            }

            fclose($handle);
        }, 'alunos-sem-turno.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function rowsQuery()
    {
        $schoolYearId = SchoolYear::query()->where('active', true)->value('id');

        return RegistrationSubject::query()
            ->whereNull('id_schedule')
            ->whereHas('subject', fn ($query) => $query
                ->where('student_can_enroll', true)
                ->where('status', true)
                ->when($this->subjectFilterId, fn ($subjectQuery) => $subjectQuery->whereKey($this->subjectFilterId)))
            ->whereHas('registration', fn ($query) => $query->where('id_schoolyear', $schoolYearId))
            ->with(['subject', 'registration.student', 'registration.class'])
            ->when($this->search !== '', fn ($query) => $query->where(function ($query): void {
                $query->whereHas('subject', fn ($subjectQuery) => $subjectQuery->where('name', 'like', '%'.$this->search.'%'))
                    ->orWhereHas('registration.student', function ($studentQuery): void {
                        $studentQuery->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('number', 'like', '%'.$this->search.'%');
                    });
            }))
            ->orderBy('id');
    }
}
