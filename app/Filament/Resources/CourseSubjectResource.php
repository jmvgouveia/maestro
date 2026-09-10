<?php

namespace App\Filament\Resources;

use App\Filament\Imports\CourseSubjectImporter;
use App\Filament\Resources\CourseSubjectResource\Pages;
use App\Models\CourseSubject;
use App\Filament\Concerns\HasSchoolYearHistory;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CourseSubjectResource extends Resource
{
    use HasSchoolYearHistory;
    protected static ?string $model = CourseSubject::class;

    protected static ?string $navigationGroup = 'Gestão Pedagógica';
    protected static ?string $navigationLabel = 'Disciplinas - Curso';
    protected static ?string $navigationIcon = 'heroicon-s-clipboard-document-check';
    protected static ?int $navigationSort = 4;

    public static function getLabel(): string
    {
        return 'Disciplina do Curso';
    }

    public static function getPluralLabel(): string
    {
        return 'Disciplinas do Curso';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('id_course')
                    ->label('Curso')
                    ->required()
                    ->relationship('course', 'name')
                    ->placeholder('Selecione o Curso')
                    ->reactive(),
                Select::make('id_subject')
                    ->label('Disciplina')
                    ->required()
                    ->relationship('subject', 'name')
                    ->placeholder('Selecione a disciplina')
                    ->reactive(),
                Select::make('id_schoolyear')
                    ->label('Ano Lectivo')
                    ->required()
                    ->relationship('schoolyear', 'schoolyear', fn (Builder $query) => $query->where('active', true))
                    ->default(fn () => static::activeSchoolYearId())
                    ->disabled()
                    ->dehydrated()
                    ->placeholder('Ano Lectivo'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('schoolyear.schoolyear')
                    ->label('Ano Letivo')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('course.name')
                    ->label('Curso')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('subject.name')
                    ->label('Disciplina')
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('id_schoolyear')
                    ->label('Ano Letivo')
                    ->options(fn () => static::schoolYearOptions())
                    ->default(static::activeSchoolYearId())
                    ->selectablePlaceholder(false)
                    ->visible(fn () => static::canBrowseSchoolYearHistory()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record, $livewire) => ! $livewire->isHistoricalMode()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn ($livewire) => ! $livewire->isHistoricalMode()),
                ]),
            ])
            ->headerActions([
                Tables\Actions\ImportAction::make()
                    ->importer(CourseSubjectImporter::class)
                    ->label('Importar Disciplinas - Curso')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('forest_green')
                    ->visible(fn ($livewire) => ! $livewire->isHistoricalMode()),

            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return static::canBrowseSchoolYearHistory()
            ? $query
            : $query->where('id_schoolyear', static::activeSchoolYearId() ?? 0);
    }

    public static function canEdit(Model $record): bool
    {
        return static::isActiveSchoolYear($record->id_schoolyear) && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::isActiveSchoolYear($record->id_schoolyear) && parent::canDelete($record);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourseSubjects::route('/'),
            'create' => Pages\CreateCourseSubject::route('/create'),
            'edit' => Pages\EditCourseSubject::route('/{record}/edit'),
        ];
    }
}
