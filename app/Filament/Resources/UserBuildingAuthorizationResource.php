<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserBuildingAuthorizationResource\Pages;
use App\Models\Building;
use App\Models\User;
use App\Models\UserBuildingAuthorization;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserBuildingAuthorizationResource extends Resource
{
    protected static ?string $model = UserBuildingAuthorization::class;

    protected static ?string $navigationGroup = 'Porteiro';
    protected static ?string $navigationLabel = 'Autorizações de Edifícios';
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return 'Autorização de Edifício';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Autorizações de Edifícios';
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user?->can('manage user room authorizations') ?? false;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->with(['user', 'building', 'createdBy']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('user_id')
                ->label('Porteiro')
                ->relationship('user', 'name', fn ($query) => $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'Porteiro')))
                ->required()
                ->searchable()
                ->preload(),
            Select::make('building_id')
                ->label('Edifício')
                ->relationship('building', 'address')
                ->required()
                ->searchable()
                ->preload(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Porteiro')->searchable()->sortable(),
                TextColumn::make('building.address')->label('Morada')->searchable()->sortable(),
                TextColumn::make('createdBy.name')->label('Criado por')->placeholder('-'),
                TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('user.name')
            ->filters([
                Tables\Filters\SelectFilter::make('user')
                    ->label('Porteiro')
                    ->relationship('user', 'name', fn ($query) => $query->whereHas('roles', fn ($roleQuery) => $roleQuery->where('name', 'Porteiro')))
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('building')
                    ->label('Edifício')
                    ->relationship('building', 'address')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('createAuthorization')
                    ->label('Adicionar autorização')
                    ->icon('heroicon-o-plus')
                    ->url(fn (): string => static::getUrl('create'))
                    ->visible(fn (): bool => auth()->user()?->can('create', UserBuildingAuthorization::class) ?? false),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserBuildingAuthorizations::route('/'),
            'create' => Pages\CreateUserBuildingAuthorization::route('/create'),
            'edit' => Pages\EditUserBuildingAuthorization::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create', UserBuildingAuthorization::class) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('update', $record) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('delete', $record) ?? false;
    }
}
