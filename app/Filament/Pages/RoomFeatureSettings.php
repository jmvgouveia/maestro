<?php

namespace App\Filament\Pages;

use App\Models\RoomFeature;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RoomFeatureSettings extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.room-feature-settings';
    protected static ?string $slug = 'caracteristicas-salas';
    protected static ?string $navigationGroup = 'Porteiro';
    protected static ?string $navigationLabel = 'Características de salas';
    protected static ?string $title = 'Características de salas';
    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage room occupancy settings') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(RoomFeature::query()->withCount('rooms'))
            ->columns([
                TextColumn::make('visual')->label('Visual')->getStateUsing(fn (RoomFeature $record): string => $record->icon_path ? 'Ficheiro' : ($record->icon ? 'Ícone: '.$record->icon : 'Letra: '.$record->symbol))->badge()->color('primary'),
                TextColumn::make('name')->label('Característica')->searchable()->sortable(),
                TextColumn::make('rooms_count')->label('Salas associadas')->sortable(),
            ])
            ->headerActions([
                Action::make('create')->label('Adicionar característica')->icon('heroicon-o-plus')->form($this->featureForm())->action(fn (array $data): RoomFeature => RoomFeature::create($data)),
            ])
            ->actions([
                Action::make('edit')->label('Editar')->icon('heroicon-o-pencil-square')->fillForm(fn (RoomFeature $record): array => $record->only(['name', 'symbol']))->form($this->featureForm())->action(function (RoomFeature $record, array $data): void {
                    $record->update($data);
                    Notification::make()->title('Característica atualizada.')->success()->send();
                }),
                DeleteAction::make()->label('Eliminar'),
            ])
            ->defaultSort('name');
    }

    private function featureForm(): array
    {
        return [
            TextInput::make('name')->label('Nome')->required()->maxLength(100),
            Select::make('icon')->label('Ícone')->placeholder('Usar letra/símbolo')->options([
                'musical-note' => 'Nota musical',
                'academic-cap' => 'Educação/teoria',
                'building-office-2' => 'Edifício',
                'tag' => 'Etiqueta',
                'star' => 'Estrela',
                'sparkles' => 'Destaque',
            ])->nullable(),
            TextInput::make('symbol')->label('Letra/símbolo')->helperText('Use uma letra ou símbolo curto se não escolher um ícone.')->nullable()->maxLength(4),
            FileUpload::make('icon_path')->label('Ficheiro do ícone')->disk('public')->directory('room-feature-icons')->acceptedFileTypes(['image/png', 'image/x-icon'])->maxSize(256)->openable()->downloadable()->helperText('Aceita PNG ou ICO. O ficheiro tem prioridade sobre o ícone e a letra.'),
        ];
    }
}
