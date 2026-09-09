<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SchoolYearResource\Pages;
use App\Models\SchoolYear;
use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class SchoolYearResource extends Resource
{
    protected static ?string $model = SchoolYear::class;

    protected static ?string $navigationGroup = 'Administração';

    protected static ?string $navigationLabel = 'Anos Letivos';

    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?int $navigationSort = 3;

    public static function getLabel(): string
    {
        return 'Ano Lecivo';
    }

    public static function getPluralLabel(): string
    {
        return 'Anos Letivos';
    }

    private static function scheduleWindow(string $label, string $key): Section
    {
        $start = "start_date_{$key}";
        $end = "end_date_{$key}";

        return Section::make($label)
            ->schema([
                DatePicker::make($start)
                    ->label('Data de Início')
                    ->required()
                    ->rule(function (Get $get) use ($end): Closure {
                        return function (string $attribute, mixed $value, Closure $fail) use ($get, $end): void {
                            $endValue = $get($end);

                            if ($value && $endValue && Carbon::parse($value)->gt(Carbon::parse($endValue))) {
                                $fail('A data de início deve ser anterior ou igual à data de fim.');
                            }
                        };
                    })
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $get) use ($end): void {
                        $endValue = $get($end);

                        if ($state && $endValue && Carbon::parse($state)->gt(Carbon::parse($endValue))) {
                            throw ValidationException::withMessages([
                                $end => 'A data de início deve ser anterior ou igual à data de fim.',
                            ]);
                        }
                    }),
                DatePicker::make($end)
                    ->label('Data de Fim')
                    ->required()
                    ->rule(function (Get $get) use ($start): Closure {
                        return function (string $attribute, mixed $value, Closure $fail) use ($get, $start): void {
                            $startValue = $get($start);

                            if ($value && $startValue && Carbon::parse($value)->lt(Carbon::parse($startValue))) {
                                $fail('A data de fim deve ser posterior ou igual à data de início.');
                            }
                        };
                    })
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $get) use ($start, $end): void {
                        $startValue = $get($start);

                        if ($state && $startValue && Carbon::parse($state)->lt(Carbon::parse($startValue))) {
                            throw ValidationException::withMessages([
                                $end => 'A data de fim deve ser posterior ou igual à data de início.',
                            ]);
                        }
                    }),
            ])->columns(2);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Ano Letivo')
                    ->description('Defina o ano letivo')
                    ->schema([
                        TextInput::make('schoolyear')
                            ->label('Ano Letivo')
                            ->required()
                            ->regex('/^\d{4}\/\d{4}$/')
                            ->unique(ignoreRecord: true),
                        Toggle::make('active')
                            ->label('Ativo')
                            ->columnSpan(3),

                    ])->columns(2),

                Section::make('Marcação de Horários Docentes')
                    ->description('Defina as datas por tipologia de curso.')
                    ->schema([
                        self::scheduleWindow('Especializado', 'especializado'),
                        self::scheduleWindow('Profissional', 'profissional'),
                        self::scheduleWindow('Livre', 'livre'),
                    ])->columns(1),

                Section::make('Marcação de Horários Alunos')
                    ->description('Defina as datas de início e fim para a marcação de horários pelos Alunos')
                    ->schema([
                        DatePicker::make('start_date_registration')
                            ->label('Data de Início')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(
                                function ($state, callable $set, callable $get) {
                                    $startYear = Carbon::parse($state)->year;
                                    $end = $get('end_date_registration');

                                    if ($end) {
                                        $endYear = Carbon::parse($end)->year;

                                        if ($startYear === $endYear) {

                                            throw ValidationException::withMessages([
                                                'end_date_registration' => 'As datas devem estar em anos diferentes.',
                                            ]);
                                        }
                                    }
                                }
                            ),
                        DatePicker::make('end_date_registration')
                            ->label('Data de Fim')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(
                                function ($state, callable $set, callable $get) {
                                    $endYear = Carbon::parse($state)->year;
                                    $start = $get('start_date_registration');

                                    if ($start) {
                                        $startYear = Carbon::parse($start)->year;

                                        if ($startYear === $endYear) {

                                            throw ValidationException::withMessages([
                                                'end_date_registration' => 'As datas devem estar em anos diferentes.',
                                            ]);
                                        }
                                    }
                                }
                            ),

                    ])->columns(2),

            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('schoolyear')
                    ->label('Ano letivo'),
                TextColumn::make('start_date_especializado')
                    ->label('Especializado: início'),
                TextColumn::make('end_date_especializado')
                    ->label('Especializado: fim'),
                TextColumn::make('start_date_profissional')
                    ->label('Profissional: início'),
                TextColumn::make('end_date_profissional')
                    ->label('Profissional: fim'),
                TextColumn::make('start_date_livre')
                    ->label('Livre: início'),
                TextColumn::make('end_date_livre')
                    ->label('Livre: fim'),
                ToggleColumn::make('active')
                    ->label('Ativo')
                    ->disabled(),
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
            'index' => Pages\ListSchoolYears::route('/'),
            'create' => Pages\CreateSchoolYear::route('/create'),
            'edit' => Pages\EditSchoolYear::route('/{record}/edit'),
        ];
    }
}
