<?php

namespace App\Filament\Resources;

use App\Filament\Imports\CourseImporter;
use App\Filament\Resources\CourseResource\Pages;
use App\Models\Course;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class CourseResource extends Resource
{
    protected static ?string $model = Course::class;

    protected static ?string $navigationGroup = 'Académico';

    protected static ?string $navigationLabel = 'Cursos';

    protected static ?string $navigationIcon = 'heroicon-s-academic-cap';

    protected static ?int $navigationSort = 2;

    public static function getLabel(): string
    {
        return 'Curso';
    }

    public static function getPluralLabel(): string
    {
        return 'Cursos';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nome')
                    ->maxLength(255)
                    ->required()
                    ->placeholder('Introduza nome'),
                Select::make('type')
                    ->label('Tipologia')
                    ->options(Course::types())
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')
                    ->label('Nome')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipologia')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('setType')
                        ->label('Definir tipologia')
                        ->icon('heroicon-o-tag')
                        ->form([
                            Select::make('type')
                                ->label('Tipologia')
                                ->options(Course::types())
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each(fn (Course $course) => Gate::authorize('update', $course));

                            $records->each->update([
                                'type' => $data['type'],
                            ]);
                        })
                        ->deselectRecordsAfterCompletion()
                        ->successNotificationTitle('Tipologia atribuída aos cursos.'),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Tables\Actions\ImportAction::make()
                    ->importer(CourseImporter::class)
                    ->label('Importar Cursos')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('forest_green'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourses::route('/'),
            'create' => Pages\CreateCourse::route('/create'),
            'edit' => Pages\EditCourse::route('/{record}/edit'),
        ];
    }
}
