<?php

namespace App\Filament\Pages;

use App\Filament\Resources\TeacherResource;
use App\Models\Room;
use App\Models\SchoolYear;
use App\Models\User;
use App\Services\MergedScheduleCalendarService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;

class HorarioSobrepostoSalas extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Coordenação';

    protected static ?string $navigationLabel = 'Horário sobreposto por sala';

    protected static ?string $title = 'Horário sobreposto por sala';

    protected static string $view = 'filament.pages.horario-sobreposto-salas';

    public array $data = [
        'room_ids' => [],
    ];

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return static::hasUnrestrictedAccess($user)
            || ($user->checkPermissionTo('view room merged schedule')
                && static::coordinatorBuildingIds($user) !== []);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('selectAllRooms')
                ->label('Selecionar todas')
                ->icon('heroicon-o-check-circle')
                ->outlined()
                ->action(fn () => $this->data['room_ids'] = $this->allowedRoomIds()),
        ];
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Selecionar salas')
                ->schema([
                    Forms\Components\MultiSelect::make('room_ids')
                        ->label('Salas')
                        ->options(fn () => $this->roomOptions())
                        ->searchable()
                        ->preload()
                        ->reactive(),
                ])->columns(1),
        ])->statePath('data');
    }

    public function getMergedProperty(): ?array
    {
        $ids = $this->data['room_ids'] ?? [];
        $allowedRoomIds = $this->allowedRoomIds();
        $ids = array_values(array_intersect(
            array_map('intval', $ids),
            $allowedRoomIds,
        ));

        if (empty($ids)) {
            return null;
        }

        $merged = MergedScheduleCalendarService::buildForRooms($ids);
        $merged['roomScopes'] = Room::query()
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(fn (Room $room): array => [$room->id => $room->building?->name])
            ->all();

        return $merged;
    }

    protected function roomOptions(): array
    {
        return $this->allowedRoomQuery()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Room $room): array => [
                $room->id => $room->building
                    ? "{$room->name} ({$room->building->name})"
                    : $room->name,
            ])
            ->all();
    }

    protected function allowedRoomIds(): array
    {
        return $this->allowedRoomQuery()
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    protected function allowedRoomQuery()
    {
        $user = Filament::auth()->user();
        $activeSchoolYearId = SchoolYear::query()->where('active', true)->value('id');
        $query = Room::query();

        if (! $user instanceof User || ! $activeSchoolYearId) {
            return $query->whereRaw('0 = 1');
        }

        if (static::hasUnrestrictedAccess($user)) {
            return $query->whereHas('schedules', fn ($scheduleQuery) => $scheduleQuery
                ->where('id_schoolyear', $activeSchoolYearId)
                ->whereIn('status', ['Aprovado', 'Aprovado DP']));
        }

        $buildingIds = static::coordinatorBuildingIds($user);

        if ($buildingIds === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query
            ->whereIn('id_building', $buildingIds)
            ->whereHas('schedules', fn ($scheduleQuery) => $scheduleQuery
                ->where('id_schoolyear', $activeSchoolYearId)
                ->whereIn('status', ['Aprovado', 'Aprovado DP']));
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

    protected static function buildingCoordinatorPositionNames(): array
    {
        return TeacherResource::buildingCoordinatorPositionNames();
    }
}
