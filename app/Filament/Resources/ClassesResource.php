<?php

namespace App\Filament\Resources;

use App\Filament\Imports\ClassesImporter;
use App\Filament\Resources\ClassesResource\Pages;
use App\Models\Classes;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClassesResource extends Resource
{
    protected static ?string $model = Classes::class;

    protected static ?string $navigationGroup = 'Gestão Pedagógica';

    protected static ?string $navigationLabel = 'Turmas';

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?int $navigationSort = 1;

    public static function getLabel(): string
    {
        return 'Turma';
    }

    public static function getPluralLabel(): string
    {
        return 'Turmas';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Introduza nome'),
                Select::make('id_course')
                    ->label('Curso')
                    ->relationship('course', 'name')
                    ->placeholder('Selecione o curso')
                    ->required(),
                TextInput::make('year')
                    ->label('Ano')
                    ->numeric()
                    ->placeholder('Introduza ano'),
                Select::make('buildings')
                    ->label('Edifícios permitidos')
                    ->relationship('buildings', 'name')
                    ->multiple()
                    ->preload()
                    ->placeholder('Selecione os edifícios permitidos para esta turma')
                    ->hint('Turmas sem edifícios não aparecem na marcação de horários com turma.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('id')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('course.name')
                    ->label('Curso')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('year')
                    ->label('Ano')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('buildings.name')
                    ->label('Edifícios permitidos')
                    ->badge()
                    ->separator(',')
                    ->listWithLineBreaks()
                    ->limitList(3)
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Tables\Actions\ImportAction::make()
                    ->importer(ClassesImporter::class)
                    ->label('Importar Turmas')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('forest_green'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ClassesResource\RelationManagers\StudentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClasses::route('/'),
            'create' => Pages\CreateClasses::route('/create'),
            'edit' => Pages\EditClasses::route('/{record}/edit'),
        ];
    }
}
