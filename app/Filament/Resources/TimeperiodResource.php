<?php

namespace App\Filament\Resources;

use App\Filament\Imports\TimePeriodImporter;
use App\Filament\Resources\TimeperiodResource\Pages;
use App\Models\Timeperiod;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class TimeperiodResource extends Resource
{
    protected static ?string $model = Timeperiod::class;

    protected static ?string $navigationGroup = 'Horários';
    protected static ?string $navigationLabel = 'Períodos/Horas';
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?int $navigationSort = 4;

    public static function getLabel(): string
    {
        return 'Hora de Aula';
    }

    public static function getPluralLabel(): string
    {
        return 'Horas de Aula';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('id')
                    ->label('ID')
                    ->required()
                    ->numeric(),
                TextInput::make('description')
                    ->label('Descrição')
                    ->required()
                    ->maxLength(255),
                TextInput::make('start_time')
                    ->label('Hora de Início')
                    ->required(),
                //->time(),
                TextInput::make('end_time')
                    ->label('Hora de Fim')
                    ->required(),
                //->time(),
                Toggle::make('active')
                    ->label('Ativo')
                    ->default(true)
                    ->inline(false)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label("ID")
                    ->sortable(),
                TextColumn::make('description')
                    ->label("Descrição")
                    ->searchable(),
                TextColumn::make('start_time')
                    ->label("Hora de Início")
                    ->dateTime('H:i')
                    ->sortable(),
                TextColumn::make('end_time')
                    ->label("Hora de Fim"),
                ToggleColumn::make('active')
                    ->label('Ativo')
                    ->default(true)
                    ->inline(false)
                    ->columnSpanFull(),
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
                    ->importer(TimePeriodImporter::class)
                    ->label('Importar Períodos de Tempo')
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
            'index' => Pages\ListTimeperiods::route('/'),
            'create' => Pages\CreateTimeperiod::route('/create'),
            'edit' => Pages\EditTimeperiod::route('/{record}/edit'),
        ];
    }
}
