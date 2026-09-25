<?php

namespace App\Filament\Pages;

use App\Models\Room;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;

class RoomOccupancyMap extends Page
{
    protected static string $view = 'filament.pages.room-occupancy-map';
    protected static ?string $slug = 'mapa-ocupacao';
    protected static ?string $navigationGroup = 'Salas';
    protected static ?string $navigationLabel = 'Mapa de ocupação';
    protected static ?string $title = 'Mapa de ocupação';
    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public function getRoomsProperty(): Collection
    {
        return Room::query()
            ->with(['building', 'features', 'activeKeyControl', 'activeFloorKeyAccess'])
            ->where('show_on_occupancy_map', true)
            ->whereHas('building.userBuildingAuthorizations', fn ($query) => $query->where('user_id', auth()->id()))
            ->orderBy('id_building')->orderBy('name')
            ->get();
    }

    public function refreshOccupancy(): void
    {
        unset($this->rooms);
    }
}
