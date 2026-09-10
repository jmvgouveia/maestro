<?php

namespace App\Filament\Pages;

use App\Filament\Resources\TeacherResource;
use App\Models\Classes;
use App\Models\SchoolYear;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherHourCounter;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CoordinatedTeachers extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Coordenação';

    protected static ?string $navigationLabel = 'Docentes';

    protected static ?string $title = 'Docentes do departamento e polos/núcleos';

    protected static ?string $slug = 'docentes-coordenacao';

    protected static string $view = 'filament.pages.coordinated-teachers';

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
                || static::hasDepartmentCoordinatorPosition($user)
                || static::hasBuildingCoordinatorPosition($user)
            );
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::SevenExtraLarge;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->teacherQuery())
            ->columns([
                TextColumn::make('name')
                    ->label('Docente')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('acronym')
                    ->label('Sigla')
                    ->searchable(),
                TextColumn::make('department.name')
                    ->label('Departamento')
                    ->placeholder('Sem departamento')
                    ->sortable(),
                TextColumn::make('subjects.name')
                    ->label('Disciplinas')
                    ->badge()
                    ->separator(',')
                    ->placeholder('Sem disciplinas atribuídas'),
                TextColumn::make('approved_hours')
                    ->label('Horas letivas aprovadas')
                    ->state(fn (Teacher $record): string => $this->approvedHours($record))
                    ->tooltip('Horas aprovadas / total disponível após reduções e cargos'),
                TextColumn::make('pending_hours')
                    ->label('Pendentes')
                    ->state(fn (Teacher $record): int => $this->pendingCount($record))
                    ->sortable(),
                TextColumn::make('classes_summary')
                    ->label('Turmas')
                    ->state(fn (Teacher $record): string => $this->classesSummary($record))
                    ->wrap()
                    ->placeholder('Sem turmas atribuídas'),
            ])
            ->filters([
                SelectFilter::make('subject')
                    ->label('Disciplina')
                    ->options(fn (): array => Subject::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('schedules', fn (Builder $scheduleQuery): Builder => $this->applyAllowedScheduleScope($scheduleQuery)->where('id_subject', $data['value']))
                        : $query),
                SelectFilter::make('class')
                    ->label('Turma')
                    ->options(fn (): array => Classes::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereHas('schedules', fn (Builder $scheduleQuery): Builder => $this->applyAllowedScheduleScope($scheduleQuery)->whereHas('classes', fn (Builder $classQuery): Builder => $classQuery->whereKey($data['value'])))
                        : $query),
            ])
            ->defaultSort('name');
    }

    protected function teacherQuery(): Builder
    {
        $user = Filament::auth()->user();
        $query = Teacher::query()->with([
            'department',
            'subjects' => fn ($subjectQuery) => $subjectQuery->wherePivot('id_schoolyear', $this->activeSchoolYearId()),
        ]);

        if (! $user instanceof User || ! $this->activeSchoolYearId()) {
            return $query->whereRaw('0 = 1');
        }

        if (static::hasUnrestrictedAccess($user)) {
            return $query;
        }

        $departmentId = $user->teacher?->id_department;
        $buildingIds = static::coordinatorBuildingIds($user);
        $hasDepartmentScope = static::hasDepartmentCoordinatorPosition($user) && $departmentId;

        if (! $hasDepartmentScope && $buildingIds === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function (Builder $scopeQuery) use ($departmentId, $hasDepartmentScope, $buildingIds): void {
            if ($hasDepartmentScope) {
                $scopeQuery->where('id_department', $departmentId);
            }

            if ($buildingIds !== []) {
                $method = $hasDepartmentScope ? 'orWhereHas' : 'whereHas';
                $scopeQuery->{$method}('schedules', fn (Builder $scheduleQuery): Builder => $scheduleQuery
                    ->whereIn('status', ['Aprovado', 'Aprovado DP', 'Pendente'])
                    ->where('id_schoolyear', $this->activeSchoolYearId())
                    ->whereHas('room', fn (Builder $roomQuery): Builder => $roomQuery->whereIn('id_building', $buildingIds)));
            }
        });
    }

    protected function applyAllowedScheduleScope(Builder $query, ?array $statuses = null): Builder
    {
        $user = Filament::auth()->user();
        $yearId = $this->activeSchoolYearId();

        $query->where('id_schoolyear', $yearId)
            ->whereIn('status', $statuses ?? ['Aprovado', 'Aprovado DP', 'Pendente']);

        if (! $user instanceof User || static::hasUnrestrictedAccess($user)) {
            return $query;
        }

        $departmentId = $user->teacher?->id_department;
        $buildingIds = static::coordinatorBuildingIds($user);
        $hasDepartmentScope = static::hasDepartmentCoordinatorPosition($user) && $departmentId;

        return $query->where(function (Builder $scopeQuery) use ($departmentId, $hasDepartmentScope, $buildingIds): void {
            if ($hasDepartmentScope) {
                $scopeQuery->whereHas('teacher', fn (Builder $teacherQuery): Builder => $teacherQuery->where('id_department', $departmentId));
            }

            if ($buildingIds !== []) {
                $method = $hasDepartmentScope ? 'orWhereHas' : 'whereHas';
                $scopeQuery->{$method}('room', fn (Builder $roomQuery): Builder => $roomQuery->whereIn('id_building', $buildingIds));
            }
        });
    }

    protected function classesSummary(Teacher $teacher): string
    {
        $query = Schedule::query()->where('id_teacher', $teacher->id);
        $this->applyAllowedScheduleScope($query);

        return $query
            ->with('classes')
            ->get()
            ->flatMap(fn ($schedule) => $schedule->classes)
            ->pluck('name')
            ->unique()
            ->sort()
            ->implode(', ');
    }

    protected function approvedHours(Teacher $teacher): string
    {
        $query = Schedule::query()->where('id_teacher', $teacher->id);
        $this->applyAllowedScheduleScope($query, ['Aprovado', 'Aprovado DP']);

        $approved = $query
            ->with('subject')
            ->get()
            ->filter(fn (Schedule $schedule): bool => ! in_array(
                strtolower(trim($schedule->subject?->type ?? 'letiva')),
                ['não letiva', 'nao letiva'],
                true
            ))
            ->count();

        $remaining = TeacherHourCounter::query()
            ->where('id_teacher', $teacher->id)
            ->where('id_schoolyear', $this->activeSchoolYearId())
            ->value('teaching_load');

        return $remaining === null
            ? $approved . '/-'
            : $approved . '/' . ($approved + max((int) $remaining, 0));
    }

    protected function pendingCount(Teacher $teacher): int
    {
        $query = Schedule::query()->where('id_teacher', $teacher->id);

        return $this->applyAllowedScheduleScope($query, ['Pendente'])->count();
    }

    protected function activeSchoolYearId(): ?int
    {
        return SchoolYear::query()->where('active', true)->value('id');
    }

    protected static function hasUnrestrictedAccess(User $user): bool
    {
        return $user->hasRole('Super Admin') || $user->checkPermissionTo('view unrestricted merged schedule');
    }

    protected static function coordinatorBuildingIds(User $user): array
    {
        $schoolYearId = SchoolYear::query()->where('active', true)->value('id');

        if (! $schoolYearId || ! $user->teacher || ! static::hasBuildingCoordinatorPosition($user)) {
            return [];
        }

        return $user->teacher->coordinatorBuildings()
            ->wherePivot('id_schoolyear', $schoolYearId)
            ->pluck('buildings.id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    protected static function hasDepartmentCoordinatorPosition(User $user): bool
    {
        $schoolYearId = SchoolYear::query()->where('active', true)->value('id');

        return (bool) ($schoolYearId && $user->teacher?->id_department && $user->teacher->positions()
            ->wherePivot('id_schoolyear', $schoolYearId)
            ->whereIn('positions.name', [
                'Coordenador de Departamento',
                'Coordenador de Departamento Curricular - Até 10 docentes',
                'Coordenador Departamento Curricular (20)',
                'Coordenador Departamento Curricular (30)',
                'Coordenador Departamento Curricular (+31)',
            ])->exists());
    }

    protected static function hasBuildingCoordinatorPosition(User $user): bool
    {
        $schoolYearId = SchoolYear::query()->where('active', true)->value('id');

        return (bool) ($schoolYearId && $user->teacher && $user->teacher->positions()
            ->wherePivot('id_schoolyear', $schoolYearId)
            ->whereIn('positions.name', TeacherResource::buildingCoordinatorPositionNames())
            ->exists());
    }
}
