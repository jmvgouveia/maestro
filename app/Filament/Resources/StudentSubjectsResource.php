<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudentSubjectsResource\Pages;
use App\Models\RegistrationSubject;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StudentSubjectsResource extends Resource
{
    protected static ?string $model = RegistrationSubject::class;

    protected static ?string $navigationGroup = 'Aluno';

    protected static ?string $navigationLabel = 'As minhas disciplinas';

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('Aluno') ?? false;
    }

    public static function getLabel(): string
    {
        return 'Disciplina';
    }

    public static function getPluralLabel(): string
    {
        return 'As minhas disciplinas';
    }

    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([]);
    }

    public static function getEloquentQuery(): Builder
    {
        $studentId = auth()->user()?->student?->id;
        $schoolYearId = \App\Models\SchoolYear::query()->where('active', true)->value('id');

        if (! $studentId || ! $schoolYearId) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->with(['subject', 'registration.course', 'registration.class'])
            ->whereHas('registration', fn (Builder $query): Builder => $query
                ->where('id_student', $studentId)
                ->where('id_schoolyear', $schoolYearId));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject.name')
                    ->label('Disciplina')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('registration.course.name')
                    ->label('Curso')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('registration.class.name')
                    ->label('Turma')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('shift')
                    ->label('Turno')
                    ->placeholder('Por definir'),
            ])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('subject.name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudentSubjects::route('/'),
        ];
    }
}
