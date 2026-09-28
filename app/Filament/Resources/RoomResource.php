<?php

namespace App\Filament\Resources;

use App\Filament\Imports\RoomImporter;
use App\Filament\Resources\RoomResource\Pages;
use App\Models\Room;
use App\Models\RoomFeature;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static ?string $navigationGroup = 'GESTÃO DE CHAVES';
    protected static ?string $navigationParentItem = 'Definições';
    protected static ?string $navigationLabel = 'Sala';
    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return parent::canViewAny()
            || (auth()->user()?->can('manage room occupancy settings') ?? false);
    }

    public static function getModelLabel(): string
    {
        return 'Sala';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Salas';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength('255')
                    ->placeholder('Introduza nome'),
                Select::make('id_building')
                    ->label('Edifício')
                    ->relationship('building', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Textarea::make('description')
                    ->label('Descrição')
                    ->maxLength(1000)
                    ->placeholder('Introduza descrição')
                    ->columnSpan(2),
                Toggle::make('show_on_occupancy_map')
                    ->label('Mostrar no mapa de ocupação')
                    ->helperText('Esta opção não esconde a sala da operação ou do histórico.'),
                TextInput::make('student_key_alert_after_minutes')
                    ->label('Alerta de chave de aluno (minutos)')
                    ->numeric()
                    ->minValue(1)
                    ->nullable()
                    ->placeholder('Usar definição global')
                    ->helperText('Vazio: usa o limite global definido nas definições de controlo de chaves.'),
                CheckboxList::make('features')
                    ->label('Tipo de sala')
                    ->relationship('features', 'name')
                    ->columns(2)
                    ->columnSpan(2),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('building.name')
                    ->label('Edifício')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('features.name')
                    ->label('Características')
                    ->badge()
                    ->separator(', ')
                    ->placeholder('Sem características'),
                TextColumn::make('show_on_occupancy_map')
                    ->label('Mapa de ocupação')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Visível' : 'Oculta')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('configureOccupancy')
                    ->label('Configurar ocupação')
                    ->icon('heroicon-o-map')
                    ->visible(fn (): bool => auth()->user()?->can('manage room occupancy settings') ?? false)
                    ->fillForm(fn (Room $record): array => [
                        'show_on_occupancy_map' => $record->show_on_occupancy_map,
                        'feature_ids' => $record->features->pluck('id')->all(),
                    ])
                    ->form([
                        Toggle::make('show_on_occupancy_map')
                            ->label('Mostrar no mapa de ocupação')
                            ->helperText('Esta opção não esconde a sala da operação ou do histórico.'),
                        CheckboxList::make('feature_ids')
                            ->label('Características da sala')
                            ->options(fn (): array => RoomFeature::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->columns(2),
                    ])
                    ->action(function (Room $record, array $data): void {
                        $record->update(['show_on_occupancy_map' => (bool) ($data['show_on_occupancy_map'] ?? true)]);
                        $record->features()->sync($data['feature_ids'] ?? []);

                        Notification::make()->title('Configuração da sala guardada.')->success()->send();
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('visibility')
                        ->label('Definir visibilidade')
                        ->icon('heroicon-o-eye')
                        ->visible(fn (): bool => auth()->user()?->can('manage room occupancy settings') ?? false)
                        ->form([Toggle::make('show_on_occupancy_map')->label('Mostrar no mapa de ocupação')->required()])
                        ->action(fn ($records, array $data): mixed => $records->each->update(['show_on_occupancy_map' => (bool) $data['show_on_occupancy_map']])),
                    Tables\Actions\BulkAction::make('features')
                        ->label('Atribuir características')
                        ->icon('heroicon-o-tag')
                        ->visible(fn (): bool => auth()->user()?->can('manage room occupancy settings') ?? false)
                        ->form([CheckboxList::make('feature_ids')->label('Características')->options(fn (): array => RoomFeature::query()->orderBy('name')->pluck('name', 'id')->all())->columns(2)])
                        ->action(fn ($records, array $data): mixed => $records->each(fn (Room $room) => $room->features()->sync($data['feature_ids'] ?? []))),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Tables\Actions\ImportAction::make()
                    ->importer(RoomImporter::class)
                    ->label('Importar Salas')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('forest_green'),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['building', 'features']);
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
            'index' => Pages\ListRooms::route('/'),
            'create' => Pages\CreateRoom::route('/create'),
            'edit' => Pages\EditRoom::route('/{record}/edit'),
        ];
    }
}
