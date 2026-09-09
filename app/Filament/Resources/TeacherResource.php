<?php

namespace App\Filament\Resources;

use App\Filament\Imports\TeacherImporter;
use App\Filament\Resources\TeacherResource\Pages;
use App\Models\Building;
use App\Models\Position;
use App\Models\SchoolYear;
use App\Models\Teacher;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class TeacherResource extends Resource
{
    protected static ?string $model = Teacher::class;

    protected static ?string $navigationGroup = 'Académico';

    protected static ?string $navigationLabel = 'Professores';

    protected static ?string $navigationIcon = 'heroicon-s-users';

    protected static ?int $navigationSort = 1;

    public static function getLabel(): string
    {
        return 'Professor';
    }

    public static function getPluralLabel(): string
    {
        return 'Professores';
    }

    public static function buildingCoordinatorPositionNames(): array
    {
        return [
            'Coordenador de Polo/Núcleo - Até 50 alunos',
            'Coordenador de Polo/ Núcleo - Até 50 alunos',
            'Coordenador de Polo/Núcleo - Entre 51 e 99 alunos',
            'Coordenador de Polo/ Núcleo - Entre 51 e 99 alunos',
            'Coordenador de Polo/Núcleo - 100 ou mais alunos',
            'Coordenador de Polo/ Núcleo - 100 ou mais alunos',
        ];
    }

    public static function hasBuildingCoordinatorPosition(array $positionIds): bool
    {
        return Position::query()
            ->whereIn('id', array_filter($positionIds))
            ->whereIn('name', self::buildingCoordinatorPositionNames())
            ->exists();
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        // Oculta da navegação apenas se for professor
        if ($user && $user->hasRole('Professor')) {
            return false;
        }

        return true; // visível para admins, superadmins, etc.
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Dados pessoais')
                    ->description('Dados pessoais do professor')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Introduza nome')
                            ->columnSpan(3),
                        DatePicker::make('birthdate')
                            ->label('Data de nascimento')
                            ->before(Carbon::now()->subYears(18))
                            ->validationMessages([
                                'before' => 'Tem de ter pelo menos 18 anos de idade.',
                            ])
                            ->required(),
                        Select::make('id_gender')
                            ->label('Género')
                            ->relationship('gender', 'gender')
                            ->placeholder('Selecione o género'),
                        Select::make('id_nationality')
                            ->relationship('nationality', 'name')
                            ->label('Nacionalidade')
                            ->placeholder('Selecione o Nacionalidade'),
                    ])->columns(3),
                Section::make('Dados professor')
                    ->collapsible()
                    ->collapsed(fn () => Filament::auth()->user()?->hasRole('Professor'))
                    ->description('Dados de professor')
                    ->schema([
                        TextInput::make('number')
                            ->label('Número de professor')
                            ->required()
                            ->numeric()
                            ->placeholder('Introduza número de professor')
                            ->disabled(fn () => Filament::auth()->user()?->hasRole('Professor')),
                        TextInput::make('acronym')
                            ->label('Sigla')
                            ->required()
                            ->maxLength(20)
                            ->placeholder('Introduza sigla')
                            ->disabled(fn () => Filament::auth()->user()?->hasRole('Professor')),
                        DatePicker::make('startingdate')
                            ->label('Data de início de funções')
                            ->required()
                            ->placeholder('Selecione a data de inicio de funções')
                            ->rule(function (Get $get) {
                                $birthdate = $get('birthdate');
                                if (! $birthdate) {
                                    return null;
                                }

                                $minDate = Carbon::parse($birthdate)->addYears(18)->toDateString();

                                return 'after_or_equal:'.$minDate;
                            })
                            ->validationMessages([
                                'after_or_equal' => 'A data de início deve ser pelo menos 18 anos após a data de nascimento.',
                            ])
                            ->disabled(fn () => Filament::auth()->user()?->hasRole('Professor')),
                        Select::make('id_qualification')
                            ->relationship('qualification', 'name')
                            ->label('Habilitações')
                            ->placeholder('Selecione a Habilitação')
                            ->disabled(fn () => Filament::auth()->user()?->hasRole('Professor')),
                        Select::make('id_department')
                            ->relationship('department', 'name')
                            ->label('Departamento')
                            ->placeholder('Selecione a departamento')
                            ->disabled(fn () => Filament::auth()->user()?->hasRole('Professor')),
                        Select::make('id_professionalrelationship')
                            ->relationship('professionalrelationship', 'name')
                            ->label('Relação Profissional')
                            ->placeholder('Selecione a Relação Profissional')
                            ->disabled(fn () => Filament::auth()->user()?->hasRole('Professor')),
                        Select::make('id_contractualrelationship')
                            ->relationship('contractualrelationship', 'name')
                            ->label('Relação Contratual')
                            ->placeholder('Selecione a Relação Contratual')
                            ->disabled(fn () => Filament::auth()->user()?->hasRole('Professor')),
                        Select::make('id_salaryscale')
                            ->relationship('salaryscale', 'scale')
                            ->label('Escalão Salarial')
                            ->placeholder('Selecione a Escalão Salarial')
                            ->disabled(fn () => Filament::auth()->user()?->hasRole('Professor')),
                        Select::make('positions')
                            ->label('Cargos')
                            ->relationship('positions', 'name')
                            ->pivotData(fn (): array => [
                                'id_schoolyear' => SchoolYear::query()->where('active', true)->value('id'),
                            ])
                            ->multiple()
                            ->live()
                            ->preload()
                            ->searchable()
                            ->disabled(fn () => Filament::auth()->user()?->hasRole('Professor')),
                        Select::make('coordinator_buildings')
                            ->label('Polos/Núcleos coordenados')
                            ->helperText('Atribua os polos/núcleos ao docente quando tiver um cargo de coordenação correspondente.')
                            ->options(fn () => Building::query()
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (Building $building): array => [
                                    $building->id => "{$building->name} - {$building->address}",
                                ]))
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->disabled(fn () => Filament::auth()->user()?->hasRole('Professor')),
                        Select::make('time_reductions')
                            ->label('Reduções de Horário')
                            ->relationship('timeReductions', 'name')
                            ->pivotData(fn (): array => [
                                'id_schoolyear' => SchoolYear::query()->where('active', true)->value('id'),
                            ])
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->disabled(fn () => Filament::auth()->user()?->hasRole('Professor')),

                    ]),
                Section::make('Dados utilizador')
                    ->description('Dados de utilizador')
                    ->schema([
                        TextInput::make('user.email')
                            ->label('E-mail')
                            ->email()
                            ->required()
                            ->disabled(fn () => Filament::auth()->user()?->hasRole('Recursos Humanos'))
                            ->placeholder('Introduza e-mail'),
                    ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('Número')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nome')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('acronym')
                    ->label('Sigla')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('department.name')
                    ->label('Departamento')
                    ->wrap()
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                Tables\Actions\ImportAction::make()
                    ->importer(TeacherImporter::class)
                    ->label('Importar Professores')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('forest_green'),
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
            'index' => Pages\ListTeachers::route('/'),
            'create' => Pages\CreateTeacher::route('/create'),
            'edit' => Pages\EditTeacher::route('/{record}/edit'),
        ];
    }
}
