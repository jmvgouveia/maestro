<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HasMusicalCoordination;
use Filament\Pages\Page;

class FormacaoMusicalCoordination extends Page
{
    use HasMusicalCoordination;

    protected static ?string $navigationIcon = 'heroicon-o-musical-note';

    protected static ?string $navigationGroup = 'Coordenação';

    protected static ?string $navigationLabel = 'Formação Musical';

    protected static ?string $title = 'Coordenação de Formação Musical';

    protected static ?string $slug = 'coordenacao-formacao-musical';

    protected static string $view = 'filament.pages.musical-coordination';

    protected static function musicalSubjectName(): string
    {
        return 'Formação Musical';
    }

    protected static function musicalPermission(): string
    {
        return 'view formacao musical coordination';
    }

    protected static function musicalHasClassFilter(): bool
    {
        return true;
    }
}
