<?php

namespace App\Filament\Pages;

use App\Filament\Resources\TeacherResource;
use App\Models\SchoolYear;
use App\Models\Teacher;
use App\Models\User;
use App\Services\MergedScheduleCalendarService;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class HorarioSobreposto extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Coordenação';

    protected static ?string $navigationLabel = 'Horários';

    protected static ?string $title = 'Horário sobreposto de docentes';

    protected static string $view = 'filament.pages.horario-sobreposto';

    // Estado do formulário
    public array $data = [
        'teacher_ids' => [],
    ];

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User
            && (
                static::hasUnrestrictedAccess($user)
                || static::userHasDepartmentCoordinatorPosition($user)
                || static::userHasBuildingCoordinatorPosition($user)
            );
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Selecionar docentes')
                ->schema([
                    Forms\Components\MultiSelect::make('teacher_ids')
                        ->label('Docentes')
                        ->options(fn () => $this->teacherOptions())
                        ->searchable()
                        ->preload()
                        ->reactive(),
                ])->columns(1),
        ])->statePath('data');
    }

    // Propriedade computada Livewire: $this->merged
    public function getMergedProperty(): ?array
    {
        $ids = $this->data['teacher_ids'] ?? [];
        $allowedTeacherIds = $this->allowedTeacherIds();
        $ids = array_values(array_intersect(
            array_map('intval', $ids),
            $allowedTeacherIds,
        ));

        if (empty($ids)) {
            return null;
        }

        return MergedScheduleCalendarService::buildForTeachers($ids, $this->allowedBuildingScopes($allowedTeacherIds));
    }

    protected function teacherOptions(): array
    {
        return $this->allowedTeacherQuery()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected function allowedTeacherIds(): array
    {
        return $this->allowedTeacherQuery()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    protected function allowedTeacherQuery()
    {
        $user = Filament::auth()->user();
        $activeSchoolYearId = SchoolYear::query()->where('active', true)->value('id');
        $query = Teacher::query();

        if ($user instanceof User && static::hasUnrestrictedAccess($user)) {
            return $activeSchoolYearId
                ? $query->whereHas('schedules', fn ($scheduleQuery) => $scheduleQuery
                    ->where('id_schoolyear', $activeSchoolYearId)
                    ->whereIn('status', ['Aprovado', 'Aprovado DP']))
                : $query->whereRaw('0 = 1');
        }

        if (! $activeSchoolYearId || ! $user instanceof User) {
            return $query->whereRaw('0 = 1');
        }

        $departmentId = $user->teacher?->id_department;
        $isDepartmentCoordinator = static::userHasDepartmentCoordinatorPosition($user);
        $buildingIds = static::coordinatorBuildingIds($user);

        if (! $isDepartmentCoordinator && $buildingIds === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function ($scopeQuery) use ($activeSchoolYearId, $departmentId, $isDepartmentCoordinator, $buildingIds): void {
            if ($isDepartmentCoordinator && $departmentId) {
                $scopeQuery
                    ->where('id_department', $departmentId)
                    ->whereHas('schedules', fn ($scheduleQuery) => $scheduleQuery
                        ->where('id_schoolyear', $activeSchoolYearId)
                        ->whereIn('status', ['Aprovado', 'Aprovado DP']));
            }

            if ($buildingIds !== []) {
                $method = $isDepartmentCoordinator && $departmentId ? 'orWhereHas' : 'whereHas';
                $scopeQuery->{$method}('schedules', fn ($scheduleQuery) => $scheduleQuery
                    ->where('id_schoolyear', $activeSchoolYearId)
                    ->whereIn('status', ['Aprovado', 'Aprovado DP'])
                    ->whereHas('room', fn ($roomQuery) => $roomQuery->whereIn('id_building', $buildingIds)));
            }
        });
    }

    protected function allowedBuildingScopes(?array $teacherIds = null): array
    {
        $user = Filament::auth()->user();
        $teacherIds ??= $this->allowedTeacherIds();

        if ($user instanceof User && static::hasUnrestrictedAccess($user)) {
            return array_fill_keys($teacherIds, null);
        }

        $departmentId = $user?->teacher?->id_department;
        $isDepartmentCoordinator = $user instanceof User && static::userHasDepartmentCoordinatorPosition($user);
        $buildingIds = $user instanceof User ? static::coordinatorBuildingIds($user) : [];
        $departments = Teacher::query()
            ->whereIn('id', $teacherIds)
            ->pluck('id_department', 'id');

        return collect($teacherIds)
            ->mapWithKeys(fn (int $teacherId): array => [
                $teacherId => $isDepartmentCoordinator
                    && $departmentId
                    && (int) $departments->get($teacherId) === (int) $departmentId
                    ? null
                    : $buildingIds,
            ])
            ->all();
    }

    protected static function hasUnrestrictedAccess(User $user): bool
    {
        return $user->hasRole('Super Admin')
            || $user->checkPermissionTo('view unrestricted merged schedule');
    }

    protected static function coordinatorBuildingIds(User $user): array
    {
        $activeSchoolYearId = SchoolYear::query()->where('active', true)->value('id');

        if (! $activeSchoolYearId || ! $user->teacher) {
            return [];
        }

        $hasCoordinatorPosition = $user->teacher->positions()
            ->wherePivot('id_schoolyear', $activeSchoolYearId)
            ->whereIn('positions.name', static::buildingCoordinatorPositionNames())
            ->exists();

        if (! $hasCoordinatorPosition) {
            return [];
        }

        return $user->teacher->coordinatorBuildings()
            ->wherePivot('id_schoolyear', $activeSchoolYearId)
            ->pluck('buildings.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    protected static function userHasDepartmentCoordinatorPosition(User $user): bool
    {
        $activeSchoolYearId = SchoolYear::query()->where('active', true)->value('id');
        $teacher = $user->teacher;

        if (! $activeSchoolYearId || ! $teacher?->id_department) {
            return false;
        }

        return $teacher->positions()
            ->wherePivot('id_schoolyear', $activeSchoolYearId)
            ->whereIn('positions.name', static::coordinatorPositionNames())
            ->exists();
    }

    protected static function userHasBuildingCoordinatorPosition(User $user): bool
    {
        $activeSchoolYearId = SchoolYear::query()->where('active', true)->value('id');

        return $activeSchoolYearId !== null
            && $user->teacher !== null
            && $user->teacher->positions()
                ->wherePivot('id_schoolyear', $activeSchoolYearId)
                ->whereIn('positions.name', static::buildingCoordinatorPositionNames())
                ->exists();
    }

    protected static function coordinatorPositionNames(): array
    {
        return [
            'Coordenador de Departamento',
            'Coordenador de Departamento Curricular - Até 10 docentes',
            'Coordenador Departamento Curricular (20)',
            'Coordenador Departamento Curricular (30)',
            'Coordenador Departamento Curricular (+31)',
        ];
    }

    protected static function buildingCoordinatorPositionNames(): array
    {
        return TeacherResource::buildingCoordinatorPositionNames();
    }
}
