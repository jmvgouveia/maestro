<?php

namespace App\Filament\Pages;

use App\Filament\Resources\TeacherResource;
use App\Models\SchoolYear;
use App\Models\Teacher;
use App\Models\User;
use App\Services\MergedScheduleCalendarService;
use Filament\Actions\Action;
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('selectAllTeachers')
                ->label('Selecionar todos')
                ->icon('heroicon-o-check-circle')
                ->outlined()
                ->action(fn (): array => $this->data['teacher_ids'] = $this->allowedTeacherIds()),
            Action::make('selectDepartmentTeachers')
                ->label('Selecionar departamento')
                ->icon('heroicon-o-user-group')
                ->outlined()
                ->visible(fn (): bool => static::userHasDepartmentCoordinatorPosition(Filament::auth()->user()))
                ->action(fn (): array => $this->data['teacher_ids'] = $this->departmentTeacherIds()),
            Action::make('selectBuildingTeachers')
                ->label('Selecionar Polo/Núcleo')
                ->icon('heroicon-o-building-office-2')
                ->outlined()
                ->visible(fn (): bool => static::userHasBuildingCoordinatorPosition(Filament::auth()->user()))
                ->action(fn (): array => $this->data['teacher_ids'] = $this->buildingTeacherIds()),
        ];
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

        $merged = MergedScheduleCalendarService::buildForTeachers($ids, $this->allowedBuildingScopes($allowedTeacherIds));
        $merged['teacherScopes'] = Teacher::query()
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(fn (Teacher $teacher): array => [$teacher->id => $this->teacherScopeFor($teacher)])
            ->all();

        return $merged;
    }

    protected function teacherOptions(): array
    {
        $user = Filament::auth()->user();
        $activeSchoolYearId = SchoolYear::query()->where('active', true)->value('id');
        $buildingIds = $user instanceof User ? static::coordinatorBuildingIds($user) : [];
        $isDepartmentCoordinator = $user instanceof User && static::userHasDepartmentCoordinatorPosition($user);
        $departmentId = $user?->teacher?->id_department;
        $isUnrestricted = $user instanceof User && static::hasUnrestrictedAccess($user);

        return $this->allowedTeacherQuery()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (Teacher $teacher) use ($isUnrestricted, $activeSchoolYearId, $isDepartmentCoordinator, $departmentId, $buildingIds): array {
                $scope = $isUnrestricted ? null : static::teacherScopeLabel(
                    $teacher,
                    $activeSchoolYearId,
                    $isDepartmentCoordinator,
                    $departmentId,
                    $buildingIds,
                );

                return [$teacher->id => $scope ? "{$teacher->name} - {$scope}" : $teacher->name];
            })
            ->all();
    }

    protected function allowedTeacherIds(): array
    {
        return $this->allowedTeacherQuery()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    protected function departmentTeacherIds(): array
    {
        $user = Filament::auth()->user();
        $schoolYearId = SchoolYear::query()->where('active', true)->value('id');
        $departmentId = $user?->teacher?->id_department;

        if (! $schoolYearId || ! $departmentId) {
            return [];
        }

        return Teacher::query()
            ->where('id_department', $departmentId)
            ->whereHas('schedules', fn ($query) => $query
                ->where('id_schoolyear', $schoolYearId)
                ->whereIn('status', ['Aprovado', 'Aprovado DP']))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    protected function buildingTeacherIds(): array
    {
        $user = Filament::auth()->user();
        $schoolYearId = SchoolYear::query()->where('active', true)->value('id');
        $buildingIds = $user instanceof User ? static::coordinatorBuildingIds($user) : [];

        if (! $schoolYearId || $buildingIds === []) {
            return [];
        }

        return Teacher::query()
            ->whereHas('schedules', fn ($query) => $query
                ->where('id_schoolyear', $schoolYearId)
                ->whereIn('status', ['Aprovado', 'Aprovado DP'])
                ->whereHas('room', fn ($roomQuery) => $roomQuery->whereIn('id_building', $buildingIds)))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
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
        return $user->hasAnyRole(['Super Admin', 'Recursos Humanos', 'Área Pedagógica', 'Gestor Conflitos'])
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

    protected static function teacherScopeLabel(
        Teacher $teacher,
        ?int $schoolYearId,
        bool $isDepartmentCoordinator,
        mixed $departmentId,
        array $buildingIds,
    ): string {
        if (! $schoolYearId) {
            return 'Sem ano letivo ativo';
        }

        $departmentScope = $isDepartmentCoordinator
            && $departmentId
            && (int) $teacher->id_department === (int) $departmentId
            && $teacher->schedules()
                ->where('id_schoolyear', $schoolYearId)
                ->whereIn('status', ['Aprovado', 'Aprovado DP'])
                ->exists();

        $buildingScope = $buildingIds !== []
            && $teacher->schedules()
                ->where('id_schoolyear', $schoolYearId)
                ->whereIn('status', ['Aprovado', 'Aprovado DP'])
                ->whereHas('room', fn ($query) => $query->whereIn('id_building', $buildingIds))
                ->exists();

        return match (true) {
            $departmentScope && $buildingScope => 'Departamento + Polo/Núcleo',
            $departmentScope => 'Departamento',
            $buildingScope => 'Polo/Núcleo',
            default => '',
        };
    }

    protected function teacherScopeFor(Teacher $teacher): string
    {
        $user = Filament::auth()->user();
        $schoolYearId = SchoolYear::query()->where('active', true)->value('id');
        $buildingIds = $user instanceof User ? static::coordinatorBuildingIds($user) : [];

        return static::teacherScopeLabel(
            $teacher,
            $schoolYearId,
            $user instanceof User && static::userHasDepartmentCoordinatorPosition($user),
            $user?->teacher?->id_department,
            $buildingIds,
        );
    }

    protected static function buildingCoordinatorPositionNames(): array
    {
        return TeacherResource::buildingCoordinatorPositionNames();
    }
}
