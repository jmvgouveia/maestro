<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KeyControlResource\Pages;
use App\Models\Building;
use App\Models\KeyControl;
use App\Models\Room;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Models\UserBuildingAuthorization;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KeyControlResource extends Resource
{
    protected static ?string $model = KeyControl::class;

    protected static ?string $navigationGroup = 'Porteiro';

    protected static ?string $navigationLabel = 'Movimentos';

    protected static ?string $navigationIcon = 'heroicon-o-key';

    public static function getModelLabel(): string
    {
        return 'Registo de Chave';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Registos de Chaves';
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return ($user?->isPorter() || $user?->isKeyManager())
            && ($user?->can('view-any key control') ?? false);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['room.building', 'holder', 'pickedUpBy', 'returnedBy', 'correctedBy', 'originalKeyControl']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('room_id')
                    ->label('Sala')
                    ->relationship('room', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('holder_type')
                    ->label('Tipo')
                    ->options([
                        Teacher::class => 'Professor',
                        Student::class => 'Aluno',
                    ])
                    ->required()
                    ->live(),
                Select::make('holder_id')
                    ->label('Entregue a')
                    ->searchable()
                    ->required()
                    ->options(function (Forms\Get $get): array {
                        return static::holderOptions($get('holder_type'));
                    })
                    ->live(),
                DateTimePicker::make('picked_up_at')
                    ->label('Levantamento')
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y H:i'),
                DateTimePicker::make('returned_at')
                    ->label('Devolução')
                    ->native(false)
                    ->displayFormat('d/m/Y H:i')
                    ->nullable(),
                Textarea::make('pick_up_observations')
                    ->label('Observações no levantamento')
                    ->maxLength(1000)
                    ->columnSpanFull(),
                Textarea::make('return_observations')
                    ->label('Observações na devolução')
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('room.name')
                    ->label('Sala')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('room.building.name')
                    ->label('Edifício')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('holder_name')
                    ->label('Entregue a')
                    ->getStateUsing(fn (KeyControl $record): string => $record->holderDisplayName())
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query
                            ->whereHasMorph('holder', [Teacher::class, Student::class], function (Builder $query) use ($search): void {
                                $query->where('name', 'like', "%{$search}%");
                            });
                    }),
                TextColumn::make('holder_type_label')
                    ->label('Tipo')
                    ->getStateUsing(fn (KeyControl $record): string => $record->holderTypeLabel()),
                TextColumn::make('picked_up_at')
                    ->label('Levantamento')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('returned_at')
                    ->label('Devolução')
                    ->getStateUsing(fn (KeyControl $record): ?string => $record->is_corrected
                        ? 'Corrigido'
                        : $record->returned_at?->format('d/m/Y H:i'))
                    ->badge(fn (KeyControl $record): bool => $record->is_corrected)
                    ->color(fn (KeyControl $record): string => $record->is_corrected ? 'warning' : 'gray')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('returned_at', $direction))
                    ->placeholder('Por devolver'),
                TextColumn::make('pickedUpBy.name')
                    ->label('Registado por')
                    ->placeholder('-'),
                TextColumn::make('correctedBy.name')
                    ->label('Corrigido por')
                    ->placeholder('-'),
                TextColumn::make('correction_reason')
                    ->label('Motivo da correção')
                    ->placeholder('-')
                    ->wrap(),
                TextColumn::make('originalKeyControl.id')
                    ->label('Original')
                    ->placeholder('-'),
            ])
            ->defaultSort('picked_up_at', 'desc')
            ->filters([
                Filter::make('location')
                    ->label('Localização')
                    ->form([
                        Select::make('building_id')
                            ->label('Edifício')
                            ->placeholder('Todos os edifícios')
                            ->options(fn (): array => Building::query()->orderBy('address')->pluck('address', 'id')->all())
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set): mixed => $set('room_id', null)),
                        Select::make('room_id')
                            ->label('Sala')
                            ->placeholder('Todas as salas')
                            ->options(function (Forms\Get $get): array {
                                return Room::query()
                                    ->when($get('building_id'), fn (Builder $query, $buildingId) => $query->where('id_building', $buildingId))
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->live(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['building_id'] ?? null, fn (Builder $query, $buildingId) => $query->whereHas('room', fn (Builder $roomQuery) => $roomQuery->where('id_building', $buildingId)))
                            ->when($data['room_id'] ?? null, fn (Builder $query, $roomId) => $query->where('room_id', $roomId));
                    }),
                SelectFilter::make('holder_type')
                    ->label('Tipo')
                    ->options([
                        Teacher::class => 'Professor',
                        Student::class => 'Aluno',
                    ]),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'active' => 'Levantada',
                        'returned' => 'Devolvida',
                        'corrected' => 'Corrigida',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['value'] ?? null, function (Builder $query) use ($data): Builder {
                            return match ($data['value']) {
                                'active' => $query->whereNull('returned_at')->where('is_corrected', false),
                                'returned' => $query->whereNotNull('returned_at')->where('is_corrected', false),
                                'corrected' => $query->where('is_corrected', true),
                                default => $query,
                            };
                        });
                    }),
                Filter::make('picked_up_at')
                    ->label('Data de levantamento')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('De'),
                        Forms\Components\DatePicker::make('until')->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('picked_up_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('picked_up_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('correct')
                    ->label('Corrigir')
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                     ->visible(fn (KeyControl $record): bool => ! $record->is_corrected
                         && $record->original_key_control_id === null
                         && $record->returned_at === null
                         && (auth()->user()?->can('update', $record) ?? false))
                    ->form([
                        Select::make('room_id')
                            ->label('Sala correta')
                            ->relationship('room', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Select::make('holder_type')
                            ->label('Tipo')
                            ->options([
                                Teacher::class => 'Professor',
                                Student::class => 'Aluno',
                            ])
                            ->required()
                            ->live(),
                        Select::make('holder_id')
                            ->label('Pessoa correta')
                            ->required()
                            ->searchable()
                            ->options(function (Forms\Get $get): array {
                                return static::holderOptions($get('holder_type'));
                            }),
                        Textarea::make('pick_up_observations')
                            ->label('Observações no levantamento')
                            ->maxLength(1000),
                        Textarea::make('return_observations')
                            ->label('Observações na devolução')
                            ->maxLength(1000),
                        TextInput::make('reason')
                            ->label('Motivo da correção')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->action(function (KeyControl $record, array $data): void {
                        static::correctRecord($record, $data['reason'], $data);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Corrigir registo')
                    ->modalDescription('A correção cria uma nova versão do registo e mantém o original para auditoria.')
                    ->modalSubmitActionLabel('Corrigir'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                     BulkAction::make('exportSelected')
                         ->label('Exportar selecionados')
                         ->icon('heroicon-o-arrow-down-tray')
                         ->visible(fn (): bool => auth()->user()?->isKeyManager()
                             && (auth()->user()?->can('export key control') ?? false))
                         ->action(fn (Collection $records): StreamedResponse => static::exportCsv(
                            static::getEloquentQuery()->whereKey($records->modelKeys()),
                        )),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('exportCsv')
                    ->label('Exportar filtrados')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (HasTable $livewire): StreamedResponse => static::exportCsv($livewire->getTableQueryForExport()))
                     ->visible(fn (): bool => auth()->user()?->isKeyManager()
                         && (auth()->user()?->can('export key control') ?? false)),
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
            'index' => Pages\ListKeyControls::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function correctRecord(KeyControl $record, string $reason, array $changes = []): void
    {
        /** @var User|null $user */
        $user = auth()->user();

        if ($user === null) {
            return;
        }

        if ($record->is_corrected || $record->original_key_control_id !== null || $record->returned_at !== null) {
            throw new \RuntimeException('Este registo está fechado e não pode ser corrigido.');
        }

        if (! $user->can('update', $record)) {
            throw new \RuntimeException('Só o operador que registou o movimento o pode corrigir.');
        }

        \DB::transaction(function () use ($record, $user, $reason, $changes): void {
            $roomId = (int) ($changes['room_id'] ?? $record->room_id);
            $holderType = $changes['holder_type'] ?? $record->holder_type;
            $holderId = (int) ($changes['holder_id'] ?? $record->holder_id);
            $holderName = $holderType::query()->whereKey($holderId)->value('name') ?? 'selecionado';

            if (! $user->checkPermissionTo('correct key control')
                && ! UserBuildingAuthorization::query()
                    ->where('user_id', $user->getKey())
                    ->whereHas('building.rooms', fn ($query) => $query->whereKey($roomId))
                    ->exists()) {
                throw new \RuntimeException('A sala correta não está autorizada para si.');
            }

            Room::query()->lockForUpdate()->findOrFail($roomId);

            if ($record->isActive() && KeyControl::query()
                ->where('room_id', $roomId)
                ->whereNull('returned_at')
                ->where('is_corrected', false)
                ->where($record->getKeyName(), '<>', $record->getKey())
                ->lockForUpdate()
                ->exists()) {
                throw new \RuntimeException('Esta sala já tem uma chave levantada.');
            }

            if ($record->isActive() && KeyControl::query()
                ->where('holder_type', $holderType)
                ->where('holder_id', $holderId)
                ->whereNull('returned_at')
                ->where('is_corrected', false)
                ->where($record->getKeyName(), '<>', $record->getKey())
                ->exists()) {
                throw new \RuntimeException('O utilizador '.$holderName.' já tem uma chave em sua posse.');
            }

            $correction = $record->replicate();
            $correction->room_id = $roomId;
            $correction->holder_type = $holderType;
            $correction->holder_id = $holderId;
            $correction->pick_up_observations = $changes['pick_up_observations'] ?? $record->pick_up_observations;
            $correction->return_observations = $changes['return_observations'] ?? $record->return_observations;
            $correction->original_key_control_id = $record->getKey();
            $correction->corrected_by = $user->getKey();
            $correction->correction_reason = $reason;
            $correction->is_corrected = false;
            $correction->save();

            $record->update(['is_corrected' => true]);
        });
    }

    public static function exportCsv(Builder $query): StreamedResponse
    {
        $records = $query
            ->with(['room.building', 'holder', 'pickedUpBy', 'returnedBy', 'correctedBy', 'originalKeyControl'])
            ->get();

        return response()->streamDownload(function () use ($records): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'Sala',
                'Morada do edifício',
                'Entregue a',
                'Tipo',
                'Levantamento',
                'Devolução',
                'Observações levantamento',
                'Observações devolução',
                'Registado por',
                'Devolvido por',
                'Corrigido por',
                'Motivo correção',
                'Original',
            ], ';');

            foreach ($records as $record) {
                fputcsv($handle, [
                    $record->room?->name,
                    $record->room?->building?->address,
                    $record->holderDisplayName(),
                    $record->holderTypeLabel(),
                    $record->picked_up_at?->format('d/m/Y H:i'),
                    $record->returned_at?->format('d/m/Y H:i') ?? '',
                    $record->pick_up_observations ?? '',
                    $record->return_observations ?? '',
                    $record->pickedUpBy?->name,
                    $record->returnedBy?->name,
                    $record->correctedBy?->name,
                    $record->correction_reason ?? '',
                    $record->original_key_control_id ?? '',
                ], ';');
            }

            fclose($handle);
        }, 'controlo-chaves.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private static function holderOptions(?string $holderType): array
    {
        if (! in_array($holderType, [Teacher::class, Student::class], true)) {
            return [];
        }

        return $holderType::query()
            ->orderBy('name')
            ->get(['id', 'number', 'name'])
            ->mapWithKeys(fn ($holder): array => [
                $holder->id => sprintf('%s - %s', $holder->number ?: 'Sem número', $holder->name),
            ])
            ->all();
    }
}
