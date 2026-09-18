<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasMusicalCoordination;
use Filament\Pages\Page;

class IniciacaoMusicalCoordination extends Page
{
    use HasMusicalCoordination;

    protected static ?string $navigationIcon = 'heroicon-o-musical-note';

    protected static ?string $navigationGroup = 'Coordenação';

    protected static ?string $navigationLabel = 'Iniciação Musical';

    protected static ?string $title = 'Coordenação de Iniciação Musical';

    protected static ?string $slug = 'coordenacao-iniciacao-musical';

    protected static string $view = 'filament.pages.musical-coordination';

    protected static function musicalSubjectName(): string
    {
        return 'Iniciação à Formação Musical';
    }

    protected static function musicalPermission(): string
    {
        return 'view iniciacao musical coordination';
    }
}
