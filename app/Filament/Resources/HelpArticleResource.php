<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HelpArticleResource\Pages;
use App\Models\HelpArticle;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class HelpArticleResource extends Resource
{
    protected static ?string $model = HelpArticle::class;

    protected static ?string $navigationGroup = 'Ajuda';

    protected static ?string $navigationLabel = 'Artigos de Ajuda';

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?int $navigationSort = 2;

    public static function getLabel(): string
    {
        return 'Artigo de Ajuda';
    }

    public static function getPluralLabel(): string
    {
        return 'Artigos de Ajuda';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')
                    ->label('Título')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', Str::slug($state))),
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Select::make('audience')
                    ->label('Audiência')
                    ->required()
                    ->options([
                        'todos' => 'Todos',
                        'aluno' => 'Aluno',
                        'professor' => 'Professor',
                        'administrativo' => 'Administrativo',
                    ])
                    ->default('todos'),
                TextInput::make('sort')
                    ->label('Ordenação')
                    ->required()
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->default(0),
                Toggle::make('is_published')
                    ->label('Publicado')
                    ->default(true),
                Textarea::make('content')
                    ->label('Conteúdo')
                    ->required()
                    ->rows(10)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Título')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('slug')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('audience')
                    ->label('Audiência')
                    ->sortable()
                    ->badge(),
                TextColumn::make('sort')
                    ->label('Ordenação')
                    ->sortable(),
                IconColumn::make('is_published')
                    ->label('Publicado')
                    ->boolean(),
            ])
            ->defaultSort('sort', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('audience')
                    ->label('Audiência')
                    ->options([
                        'todos' => 'Todos',
                        'aluno' => 'Aluno',
                        'professor' => 'Professor',
                        'administrativo' => 'Administrativo',
                    ]),
                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Publicado'),
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
            'index' => Pages\ListHelpArticles::route('/'),
            'create' => Pages\CreateHelpArticle::route('/create'),
            'edit' => Pages\EditHelpArticle::route('/{record}/edit'),
        ];
    }
}
