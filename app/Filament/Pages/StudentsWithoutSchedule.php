<?php

namespace App\Filament\Pages;

use App\Models\Building;
use App\Models\Classes;
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

    public ?int $buildingFilterId = null;

    public ?int $classFilterId = null;

    public string $scheduleFilter = 'all';

    public string $sortDirection = 'asc';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view students without schedule audit') ?? false;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSubjectFilterId(): void
    {
        $this->resetPage();
    }

    public function updatedBuildingFilterId(): void
    {
        $this->resetPage();
    }

    public function updatedClassFilterId(): void
    {
        $this->resetPage();
    }

    public function updatedScheduleFilter(): void
    {
        $this->resetPage();
    }

    public function sortByShift(): void
    {
        $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
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

    public function buildingOptions(): array
    {
        return Building::query()
            ->whereHas('classes.registrations', fn ($query) => $query->where('id_schoolyear', $this->activeSchoolYearId()))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function classOptions(): array
    {
        return Classes::query()
            ->whereHas('registrations', fn ($query) => $query->where('id_schoolyear', $this->activeSchoolYearId()))
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
            fputcsv($handle, ['Número', 'Aluno', 'Turma', 'Núcleo', 'Disciplina', 'Turno', 'Professor', 'E-mail do aluno'], ';');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->registration?->student?->number,
                    $row->registration?->student?->name,
                    $row->registration?->class?->name,
                    $row->registration?->class?->buildings?->pluck('name')->implode(', ') ?: '—',
                    $row->subject?->name,
                    $row->selectedSchedule?->shift ?? 'Sem turno',
                    $row->selectedSchedule?->teacher?->name ?? '—',
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
            ->leftJoin('schedules', 'schedules.id', '=', 'registrations_subjects.id_schedule')
            ->select('registrations_subjects.*')
            ->whereHas('subject', fn ($query) => $query
                ->where('student_can_enroll', true)
                ->where('status', true)
                ->when($this->subjectFilterId, fn ($subjectQuery) => $subjectQuery->whereKey($this->subjectFilterId)))
            ->whereHas('registration', fn ($query) => $query->where('id_schoolyear', $schoolYearId))
            ->when($this->scheduleFilter === 'without_schedule', fn ($query) => $query->whereNull('registrations_subjects.id_schedule'))
            ->when($this->scheduleFilter === 'with_schedule', fn ($query) => $query->whereNotNull('registrations_subjects.id_schedule'))
            ->with(['subject', 'registration.student', 'registration.class.buildings', 'selectedSchedule.teacher'])
            ->when($this->buildingFilterId, fn ($query) => $query->whereHas('registration.class.buildings', fn ($buildingQuery) => $buildingQuery->whereKey($this->buildingFilterId)))
            ->when($this->classFilterId, fn ($query) => $query->whereHas('registration', fn ($registrationQuery) => $registrationQuery->where('id_class', $this->classFilterId)))
            ->when($this->search !== '', fn ($query) => $query->where(function ($query): void {
                $query->whereHas('subject', fn ($subjectQuery) => $subjectQuery->where('name', 'like', '%'.$this->search.'%'))
                    ->orWhereHas('registration.student', function ($studentQuery): void {
                        $studentQuery->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('number', 'like', '%'.$this->search.'%');
                    });
            }))
            ->orderBy('schedules.shift', $this->sortDirection)
            ->orderBy('registrations_subjects.id');
    }

    protected function activeSchoolYearId(): ?int
    {
        return SchoolYear::query()->where('active', true)->value('id');
    }
}
