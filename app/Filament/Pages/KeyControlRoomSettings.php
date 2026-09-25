<?php

namespace App\Filament\Pages;

use App\Models\Room;
use App\Models\RoomFeature;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Filament\Notifications\Notification;

class KeyControlRoomSettings extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.key-control-room-settings';
    protected static ?string $slug = 'administracao-salas-porteiro';
    protected static ?string $navigationGroup = 'Porteiro';
    protected static ?string $navigationLabel = 'Administração de salas';
    protected static ?string $title = 'Administração de salas';
    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage room occupancy settings') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Room::query()->with(['building', 'features']))
            ->columns([
                TextColumn::make('name')->label('Sala')->searchable()->sortable(),
                TextColumn::make('building.name')->label('Edifício')->searchable()->sortable(),
                TextColumn::make('features.name')->label('Características')->badge()->separator(', ')->placeholder('Sem características'),
                TextColumn::make('show_on_occupancy_map')->label('Mapa de ocupação')->formatStateUsing(fn (bool $state): string => $state ? 'Visível' : 'Oculta')->badge()->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('id_building')
                    ->label('Edifício')
                    ->relationship('building', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Action::make('edit')
                    ->label('Configurar')
                    ->icon('heroicon-o-pencil-square')
                    ->fillForm(fn (Room $record): array => ['show_on_occupancy_map' => $record->show_on_occupancy_map, 'feature_ids' => $record->features->pluck('id')->all()])
                    ->form($this->roomForm())
                    ->action(function (Room $record, array $data): void {
                        $this->saveRoom($record, $data);
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('visibility')
                        ->label('Definir visibilidade')
                        ->icon('heroicon-o-eye')
                        ->form([Toggle::make('show_on_occupancy_map')->label('Mostrar no mapa de ocupação')->required()])
                        ->action(fn (Collection $records, array $data): mixed => $records->each->update(['show_on_occupancy_map' => (bool) $data['show_on_occupancy_map']])),
                    BulkAction::make('features')
                        ->label('Atribuir características')
                        ->icon('heroicon-o-tag')
                        ->form([CheckboxList::make('feature_ids')->label('Características')->options(fn (): array => RoomFeature::query()->orderBy('name')->pluck('name', 'id')->all())->columns(2)])
                        ->action(function (Collection $records, array $data): void {
                            $records->each(fn (Room $room) => $room->features()->sync($data['feature_ids'] ?? []));
                        }),
                ]),
            ])
            ->defaultSort('name')
            ->paginated([25, 50, 100]);
    }

    private function roomForm(): array
    {
        return [
            Toggle::make('show_on_occupancy_map')
                ->label('Mostrar no mapa de ocupação')
                ->helperText('Esta opção não esconde a sala da operação ou do histórico.'),
            CheckboxList::make('feature_ids')
                ->label('Características da sala')
                ->options(fn (): array => RoomFeature::query()->orderBy('name')->pluck('name', 'id')->all())
                ->columns(2),
        ];
    }

    private function saveRoom(Room $room, array $data): void
    {
        $room->update(['show_on_occupancy_map' => (bool) ($data['show_on_occupancy_map'] ?? true)]);
        $room->features()->sync($data['feature_ids'] ?? []);
        Notification::make()->title('Configuração da sala guardada.')->success()->send();
    }
}
