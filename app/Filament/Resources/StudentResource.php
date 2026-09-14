<?php

namespace App\Filament\Resources;

use App\Filament\Imports\GuardianConversionImporter;
use App\Filament\Imports\StudentImporter;
use App\Filament\Resources\StudentResource\Pages;
use App\Models\Student;
use App\Models\User;
use App\Services\UserActivationService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static ?string $navigationGroup = 'Gestão Pedagógica';

    protected static ?string $navigationLabel = 'Alunos';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 1;

    public static function getLabel(): string
    {
        return 'Aluno';
    }

    public static function getPluralLabel(): string
    {
        return 'Alunos';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Dados pessoais')
                    ->description('Dados pessoais do aluno')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Introduza nome')
                            ->columnSpan(3),

                        DatePicker::make('birthdate')
                            ->label('Data de nascimento')
                            ->required(),
                        Select::make('id_gender')
                            ->relationship('gender', 'gender')
                            ->label('Género')
                            ->placeholder('Selecione o género'),
                    ])->columns(3),
                Section::make('Dados aluno')
                    ->description('Dados de aluno')
                    ->schema([
                        TextInput::make('number')
                            ->label('Número de aluno')
                            ->required()
                            ->numeric()
                            ->placeholder('Introduza número de aluno'),
                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->nullable()
                            ->maxLength(255)
                            ->placeholder('Introduza e-mail'),
                    ]),
                Section::make('Encarregados de Educação')
                    ->description('Gestão dos encarregados de educação do aluno')
                    ->schema([
                        Select::make('guardians')
                            ->label('Encarregados de Educação')
                            ->relationship('guardians', 'name', fn (Builder $query): Builder => $query->role(User::ROLE_GUARDIAN))
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->name} ({$record->email})")
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Nome')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->required()
                                    ->unique(User::class, 'email'),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                $user = User::create([
                                    'name' => $data['name'],
                                    'email' => strtolower($data['email']),
                                    'password' => str()->random(40),
                                    'is_active' => false,
                                ]);
                                $user->assignRole(User::ROLE_GUARDIAN);
                                app(UserActivationService::class)->issueAndNotify($user);

                                return (int) $user->getKey();
                            })
                            ->placeholder('Selecione os encarregados de educação'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('Número de aluno')
                    ->searchable()
                    ->sortable()
                    ->width('10%'),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('guardians.name')
                    ->label('Encarregados de Educação')
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('user.id')
                    ->label('ID User')
                    ->searchable()
                    ->toggleable()
                    ->sortable(),
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
                    ->importer(StudentImporter::class)
                    ->label('Importar Alunos')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('forest_green'),
                Tables\Actions\ImportAction::make('convertGuardians')
                    ->importer(GuardianConversionImporter::class)
                    ->label('Converter EEs')
                    ->icon('heroicon-o-user-group')
                    ->color('primary'),
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
            'index' => Pages\ListStudents::route('/'),
            'create' => Pages\CreateStudent::route('/create'),
            'edit' => Pages\EditStudent::route('/{record}/edit'),
        ];
    }
}
