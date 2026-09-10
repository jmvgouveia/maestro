<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasSchoolYearHistory;
use App\Filament\Imports\TeacherSubjectsImporter;
use App\Filament\Resources\TeacherSubjectResource\Pages;
use App\Models\TeacherSubject;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TeacherSubjectResource extends Resource
{
    use HasSchoolYearHistory;

    protected static ?string $model = TeacherSubject::class;

    protected static ?string $navigationLabel = 'Professores - Disciplinas';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return auth()->user()?->isTeacher() ? 'Horários' : 'Gestão Pedagógica';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    // public static function getLabel(): string
    // {
    //     return 'Disciplina do Professor';
    // }

    public static function getPluralLabel(): string
    {
        return auth()->user()?->isTeacher()
            ? 'As minhas disciplinas'
            : 'Disciplinas do Professor';
    }

    public static function getNavigationLabel(): string
    {
        return auth()->user()?->isTeacher()
            ? 'As minhas disciplinas'
            : 'Disciplinas do Professor';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();

        if (static::canBrowseSchoolYearHistory()) {
            return $query;
        }

        if ($user?->isTeacher() && $activeYearId = static::activeSchoolYearId()) {
            return $query
                ->where('id_teacher', $user->teacher->id ?? null)
                ->where('id_schoolyear', $activeYearId);
        }

        return $query->whereRaw('1 = 0');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('id_schoolyear')
                    ->label('Ano Letivo')
                    ->required()
                    ->relationship('schoolyear', 'schoolyear', fn (Builder $query) => $query->where('active', true))
                    ->default(fn () => static::activeSchoolYearId())
                    ->disabled()
                    ->dehydrated()
                    ->placeholder('Selecione o ano letivo'),
                Select::make('id_teacher')
                    ->label('Professor')
                    ->required()
                    ->relationship('teacher', 'name')
                    ->placeholder('Selecione o professor'),
                Select::make('id_subject')
                    ->label('Disciplina')
                    ->required()
                    ->relationship('subject', 'name')
                    ->placeholder('Selecione a disciplina'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('schoolyear.schoolyear')
                    ->label('Ano Lectivo')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('teacher.name')
                    ->label('Professor')
                    ->sortable()
                    ->searchable()
                    ->visible(fn () => static::canBrowseSchoolYearHistory()),
                TextColumn::make('subject.name')
                    ->label('Disciplina')
                    ->sortable()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('subject', fn (Builder $subjectQuery): Builder => $subjectQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('acronym', 'like', "%{$search}%"));
                    }),
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
            ->headerActions([
                Tables\Actions\ImportAction::make()
                    ->importer(TeacherSubjectsImporter::class)
                    ->label('Importar Disciplinas-Professor')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('forest_green')
                    ->visible(fn ($livewire) => static::canBrowseSchoolYearHistory() && ! $livewire->isHistoricalMode()),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn ($livewire) => ! $livewire->isHistoricalMode()),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
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
            'index' => Pages\ListTeacherSubjects::route('/'),
            'create' => Pages\CreateTeacherSubject::route('/create'),
            'edit' => Pages\EditTeacherSubject::route('/{record}/edit'),
        ];
    }
}
