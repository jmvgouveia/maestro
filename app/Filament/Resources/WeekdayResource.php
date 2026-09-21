<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WeekdayResource\Pages;
use App\Models\Weekday;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WeekdayResource extends Resource
{
    protected static ?string $model = Weekday::class;

    protected static ?string $navigationGroup = 'Horários';
    protected static ?string $navigationLabel = 'Dias da semana';
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?int $navigationSort = 5;

    public static function getLabel(): string
    {
        return 'Dia da Semana';
    }

    public static function getPluralLabel(): string
    {
        return 'Dias da Semana';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('id')
                    ->label('ID')
                    ->required()
                    ->numeric(),
                TextInput::make('weekday')
                    ->label('Dia da semana')
                    ->maxLength(20)
                    ->required()
                    ->placeholder('Introduza dia da semana')
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table

            ->columns([
                TextColumn::make('id')
                    ->label("ID")
                    ->sortable(),
                TextColumn::make('weekday')
                    ->label('Dia da semana'),
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
            'index' => Pages\ListWeekdays::route('/'),
            'create' => Pages\CreateWeekday::route('/create'),
            'edit' => Pages\EditWeekday::route('/{record}/edit'),
        ];
    }
}
