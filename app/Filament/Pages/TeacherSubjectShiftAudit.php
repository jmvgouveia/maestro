<?php

namespace App\Filament\Pages;

use App\Models\Building;
use App\Models\Classes;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\Teacher;
use Filament\Pages\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeacherSubjectShiftAudit extends Page
{
    use WithPagination;

    protected static string $view = 'filament.pages.teacher-subject-shift-audit';

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static ?string $navigationGroup = 'Auditoria';

    protected static ?string $navigationLabel = 'Professor-Disciplina-Turno';

    protected static ?string $title = 'Auditoria de professor, disciplina e turno';

    protected static ?string $slug = 'auditoria-professor-disciplina-turno';

    public ?int $teacherFilterId = null;

    public ?int $classFilterId = null;

    public ?int $subjectFilterId = null;

    public ?int $buildingFilterId = null;

    public ?string $shiftFilter = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view teacher subject shift audit') ?? false;
    }

    public function updatedTeacherFilterId(): void
    {
        $this->resetPage();
    }

    public function updatedClassFilterId(): void
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

    public function updatedShiftFilter(): void
    {
        $this->resetPage();
    }

    public function teacherOptions(): array
    {
        return Teacher::query()
            ->whereHas('schedules', fn ($query) => $this->approvedScheduleScope($query))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function classOptions(): array
    {
        return Classes::query()
            ->whereHas('schedules', fn ($query) => $this->approvedScheduleScope($query))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function subjectOptions(): array
    {
        return Subject::query()
            ->whereHas('schedules', fn ($query) => $this->approvedScheduleScope($query))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function buildingOptions(): array
    {
        return Building::query()
            ->whereHas('classes.schedules', fn ($query) => $this->approvedScheduleScope($query))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function shiftOptions(): array
    {
        return $this->baseQuery()
            ->whereNotNull('schedules.shift')
            ->where('schedules.shift', '!=', '')
            ->distinct()
            ->orderBy('schedules.shift')
            ->pluck('schedules.shift', 'schedules.shift')
            ->all();
    }

    public function getRowsProperty(): LengthAwarePaginator
    {
        return $this->filteredQuery()->paginate(25);
    }

    public function exportRows(): StreamedResponse
    {
        $rows = $this->filteredQuery()->get();

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Professor', 'Disciplina', 'Turma', 'Núcleo/Pólo', 'Turno', 'Dia', 'Hora', 'Sala'], ';');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->teacher_name,
                    $row->subject_name,
                    $row->class_name,
                    $row->building_name ?: '—',
                    $row->shift ?: '—',
                    $row->weekday_name,
                    $row->timeperiod_description,
                    $row->room_name,
                ], ';');
            }

            fclose($handle);
        }, 'auditoria-professor-disciplina-turno.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function filteredQuery()
    {
        return $this->baseQuery()
            ->when($this->teacherFilterId, fn ($query) => $query->where('schedules.id_teacher', $this->teacherFilterId))
            ->when($this->classFilterId, fn ($query) => $query->where('schedules_classes.id_class', $this->classFilterId))
            ->when($this->subjectFilterId, fn ($query) => $query->where('schedules.id_subject', $this->subjectFilterId))
            ->when($this->buildingFilterId, fn ($query) => $query->whereExists(function ($subquery): void {
                $subquery->selectRaw('1')
                    ->from('class_buildings as filter_class_buildings')
                    ->whereColumn('filter_class_buildings.id_class', 'schedules_classes.id_class')
                    ->where('filter_class_buildings.id_building', $this->buildingFilterId);
            }))
            ->when($this->shiftFilter, fn ($query) => $query->where('schedules.shift', $this->shiftFilter))
            ->orderBy('teacher_name')
            ->orderBy('subject_name')
            ->orderBy('class_name')
            ->orderBy('schedules.shift');
    }

    protected function baseQuery()
    {
        $schoolYearId = SchoolYear::query()->where('active', true)->value('id');

        return DB::table('schedules')
            ->join('schedules_classes', 'schedules_classes.id_schedule', '=', 'schedules.id')
            ->join('classes', 'classes.id', '=', 'schedules_classes.id_class')
            ->join('teachers', 'teachers.id', '=', 'schedules.id_teacher')
            ->join('subjects', 'subjects.id', '=', 'schedules.id_subject')
            ->join('weekdays', 'weekdays.id', '=', 'schedules.id_weekday')
            ->join('timeperiods', 'timeperiods.id', '=', 'schedules.id_timeperiod')
            ->join('rooms', 'rooms.id', '=', 'schedules.id_room')
            ->leftJoin('class_buildings', 'class_buildings.id_class', '=', 'classes.id')
            ->leftJoin('buildings', 'buildings.id', '=', 'class_buildings.id_building')
            ->where('schedules.id_schoolyear', $schoolYearId)
            ->whereIn('schedules.status', ['Aprovado', 'Aprovado DP'])
            ->select([
                'schedules.id',
                'schedules.shift',
                'teachers.name as teacher_name',
                'subjects.name as subject_name',
                'schedules_classes.id_class',
                'classes.name as class_name',
                'weekdays.weekday as weekday_name',
                'timeperiods.description as timeperiod_description',
                'rooms.name as room_name',
                DB::raw('GROUP_CONCAT(DISTINCT buildings.name) as building_name'),
            ])
            ->groupBy([
                'schedules.id',
                'schedules.shift',
                'teachers.name',
                'subjects.name',
                'schedules_classes.id_class',
                'classes.name',
                'weekdays.weekday',
                'timeperiods.description',
                'rooms.name',
            ]);
    }

    protected function approvedScheduleScope($query)
    {
        return $query
            ->where('id_schoolyear', SchoolYear::query()->where('active', true)->value('id'))
            ->whereIn('status', ['Aprovado', 'Aprovado DP']);
    }
}
