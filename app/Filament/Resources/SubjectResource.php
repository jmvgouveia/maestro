<?php

namespace App\Filament\Resources;

use App\Filament\Imports\SubjectImporter;
use App\Filament\Resources\SubjectResource\Pages;
use App\Models\Subject;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class SubjectResource extends Resource
{
    protected static ?string $model = Subject::class;

    protected static ?string $navigationGroup = 'Gestão Pedagógica';
    protected static ?string $navigationLabel = 'Disciplinas';
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?int $navigationSort = 3;

    public static function getLabel(): string
    {
        return 'Disciplina';
    }

    public static function getPluralLabel(): string
    {
        return 'Disciplinas';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nome')
                    ->maxLength(255)
                    ->required()
                    ->placeholder('Introduza nome')
                    ->columnSpan(3),
                TextInput::make('acronym')
                    ->label('Sigla')
                    ->maxLength(30)
                    ->required()
                    ->placeholder('Ex: ABC'),
                Select::make('type')
                    ->label('Tipo')
                    ->options([
                        'Letiva' => 'Letiva',
                        'Não Letiva' => 'Não Letiva',
                    ])
                    ->default('Letiva')
                    ->required()
                    ->helperText('Selecione o tipo de disciplina'),

                Toggle::make('student_can_enroll')
                    ->label('Permite Inscrição de Alunos')
                    ->default(false)
                    ->inline(false)
                    //   ->onColor('success')
                    ->offColor('secondary'),
                //  ->helperText('Indica se a disciplina está ativa ou inativa no sistema'),

                Toggle::make('status')
                    ->label('Ativo')
                    ->default(true)
                    ->inline(false)
                    // ->onColor('success')
                    ->offColor('secondary')
                    ->helperText('Indica se a disciplina está ativa ou inativa no sistema'),

            ])->columns(3);
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
                    ->label('Disciplina')
                    ->sortable()
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('acronym')
                    ->label('Sigla')
                    ->sortable()
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->sortable()
                    ->toggleable()
                    ->searchable(),
                ToggleColumn::make('student_can_enroll')
                    ->label('Permite Inscrição de Alunos')
                    ->sortable()
                    ->toggleable(),
                ToggleColumn::make('status')
                    ->label('Ativo')
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
                    ->importer(SubjectImporter::class)
                    ->label('Importar Disciplinas')
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
            'index' => Pages\ListSubjects::route('/'),
            'create' => Pages\CreateSubject::route('/create'),
            'edit' => Pages\EditSubject::route('/{record}/edit'),
        ];
    }
}
