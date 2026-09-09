<?php

namespace App\Filament\Pages;

use App\Models\HelpArticle;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class HelpCenter extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?string $navigationLabel = 'Ajuda';

    protected static ?string $navigationGroup = 'Ajuda';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.help-center';

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->hasAnyRole([
            'Super Admin',
            'Secretaria',
            'Área Pedagógica',
            'Recursos Humanos',
            'Gestor Conflitos',
            'Professor',
            'Aluno',
        ]);
    }

    public function getArticles()
    {
        return HelpArticle::query()
            ->published()
            ->forAudience($this->audience())
            ->orderBy('sort')
            ->orderBy('title')
            ->get();
    }

    private function audience(): string
    {
        $user = Filament::auth()->user();

        return match (true) {
            $user?->hasRole('Aluno') => 'aluno',
            $user?->hasRole('Professor') => 'professor',
            default => 'administrativo',
        };
    }
}
