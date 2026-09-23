<?php

namespace App\Filament\Resources\KeyControlResource\Pages;

use App\Filament\Resources\KeyControlResource;
use Filament\Resources\Pages\ListRecords;

class ListKeyControls extends ListRecords
{
    protected static string $resource = KeyControlResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Criação e exportação são controladas pelo resource/table.
        ];
    }
}
