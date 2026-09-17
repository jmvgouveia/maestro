<?php

namespace App\Filament\Pages;

use App\Filament\Resources\TeacherResource;
use App\Models\Building;
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
        'building_id' => null,
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
                ->action(fn () => $this->data['room_ids'] = $this->allowedRoomIds($this->data['building_id'] ?? null)),
        ];
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Selecionar salas')
                ->schema([
                    Forms\Components\Select::make('building_id')
                        ->label('Polo/Núcleo')
                        ->options(fn () => $this->buildingOptions())
                        ->searchable()
                        ->placeholder('Todos os polos/núcleos')
                        ->visible(fn () => static::hasUnrestrictedAccess(Filament::auth()->user()))
                        ->live()
                        ->afterStateUpdated(fn (callable $set) => $set('room_ids', [])),
                    Forms\Components\MultiSelect::make('room_ids')
                        ->label('Salas')
                        ->options(fn (callable $get) => $this->roomOptions($get('building_id')))
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
            ->mapWithKeys(function (Room $room): array {
                $building = $room->building;
                $label = $building?->address ?: $building?->name;

                if ($building?->address && $building->name) {
                    $label .= " ({$building->name})";
                }

                return [$room->id => $label];
            })
            ->all();

        return $merged;
    }

    protected function buildingOptions(): array
    {
        $activeSchoolYearId = SchoolYear::query()->where('active', true)->value('id');

        if (! $activeSchoolYearId) {
            return [];
        }

        return Building::query()
            ->whereHas('rooms.schedules', fn ($scheduleQuery) => $scheduleQuery
                ->where('id_schoolyear', $activeSchoolYearId)
                ->whereIn('status', ['Aprovado', 'Aprovado DP']))
            ->orderBy('name')
            ->get(['id', 'name', 'address'])
            ->mapWithKeys(function (Building $building): array {
                $label = $building->address ?: $building->name;

                if ($building->address && $building->name) {
                    $label .= " ({$building->name})";
                }

                return [$building->id => $label];
            })
            ->all();
    }

    protected function roomOptions(?int $buildingId = null): array
    {
        return $this->allowedRoomQuery($buildingId)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Room $room): array => [
                $room->id => $room->building
                    ? "{$room->name} ({$room->building->name})"
                    : $room->name,
            ])
            ->all();
    }

    protected function allowedRoomIds(?int $buildingId = null): array
    {
        return $this->allowedRoomQuery($buildingId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    protected function allowedRoomQuery(?int $buildingId = null)
    {
        $user = Filament::auth()->user();
        $activeSchoolYearId = SchoolYear::query()->where('active', true)->value('id');
        $query = Room::query();

        if (! $user instanceof User || ! $activeSchoolYearId) {
            return $query->whereRaw('0 = 1');
        }

        if (static::hasUnrestrictedAccess($user)) {
            return $query
                ->when($buildingId !== null, fn ($roomQuery) => $roomQuery->where('id_building', $buildingId))
                ->whereHas('schedules', fn ($scheduleQuery) => $scheduleQuery
                    ->where('id_schoolyear', $activeSchoolYearId)
                    ->whereIn('status', ['Aprovado', 'Aprovado DP']));
        }

        $buildingIds = static::coordinatorBuildingIds($user);

        if ($buildingIds === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query
            ->whereIn('id_building', $buildingIds)
            ->when($buildingId !== null, fn ($roomQuery) => $roomQuery->where('id_building', $buildingId))
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
