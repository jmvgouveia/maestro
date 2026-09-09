<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScheduleResource\Pages;
use App\Helpers\DatabaseHelper as DBHelper;
use App\Helpers\UserHelper;
use App\Models\Building;
use App\Models\Classes;
use App\Models\Registration;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Timeperiod;
use App\Models\Weekday;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Actions as ActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScheduleResource extends Resource
{
    protected static ?string $model = Schedule::class;

    protected static ?string $navigationGroup = 'Horários';

    protected static ?string $navigationLabel = 'Marcação de Horários';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function shouldRegisterNavigation(): bool
    {
        return ! Auth::user()?->isTeacher();
    }

    public ?Schedule $conflictingSchedule = null;

    public static function getLabel(): string
    {
        return 'Marcação de Horários';
    }

    public static function getPluralLabel(): string
    {
        return 'Marcações de Horários';
    }

    public static function exportSchedules(?Collection $records = null): StreamedResponse
    {
        Gate::authorize('export', Schedule::class);

        // $anoLetivoAtivoId = \App\Models\SchoolYear::where('active', true)->value('id');
        $anoLetivoAtivoId = DBHelper::getIDActiveSchoolyear();

        $query = static::getEloquentQuery()
            ->whereIn('status', ['Aprovado', 'Aprovado DP'])
            ->where('id_schoolyear', $anoLetivoAtivoId);

        if ($records !== null) {
            $query->whereKey($records->modelKeys());
        }

        $schedules = $query
            ->with(['teacher', 'room', 'subject', 'weekday', 'timePeriod', 'classes', 'students'])
            ->get();

        $now = now()->format('Y-m-d_H-i');
        $filename = "horarios-{$now}.txt";

        return response()->streamDownload(function () use ($schedules) {
            $handle = fopen('php://output', 'w');

            foreach ($schedules as $schedule) {
                $turmaAlunos = [];

                if ($schedule->students->isNotEmpty()) {
                    foreach ($schedule->students as $student) {
                        $registration = Registration::where('id_student', $student->id)
                            ->where('id_schoolyear', $schedule->id_schoolyear)
                            ->whereIn('id_class', $schedule->classes->pluck('id'))
                            ->with('class')
                            ->first();

                        if ($registration && $registration->class) {
                            $turmaNome = $registration->class->name;
                            $turmaAno = $registration->class->year;

                            $turmaAlunos[$turmaNome]['ano'] = $turmaAno;
                            $turmaAlunos[$turmaNome]['alunos'][] = "{$student->number}";
                        }
                    }
                } else {
                    foreach ($schedule->classes as $class) {
                        $linha = [
                            $schedule->id_weekday + 2,
                            $schedule->id_timeperiod - 36,
                            "\"{$class->name}\"",
                            $class->year,
                            "\"{$schedule->teacher->number}\"",
                            "\"{$schedule->subject->acronym}\"",
                            "\"{$schedule->room->name}\"",
                            '""',
                            '""',
                        ];

                        fwrite($handle, implode('|', $linha)."\n");
                    }

                    continue;
                }

                foreach ($turmaAlunos as $turma => $info) {
                    $linha = [
                        $schedule->id_weekday + 2,
                        $schedule->id_timeperiod - 36,
                        "\"$turma\"",
                        $info['ano'],
                        "\"{$schedule->teacher->number}\"",
                        "\"{$schedule->subject->acronym}\"",
                        "\"{$schedule->room->name}\"",
                        '""',
                        '"'.implode(',', $info['alunos']).'"',
                    ];

                    fwrite($handle, implode('|', $linha)."\n");
                }
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = Auth::user();

        if ($user?->hasRole('Aluno') && ! $user->isSuperAdmin()) {
            $query->whereRaw('0 = 1');
        } elseif ($user?->isTeacher() && ! $user->isSuperAdmin()) {
            $teacherId = $user->teacher?->getKey();

            if ($teacherId === null) {
                $query->whereRaw('0 = 1');
            } else {
                $query->where('id_teacher', $teacherId);
            }
        }

        // Obtém o ano letivo ativo (ajusta conforme o teu modelo)
        $anoLetivoAtivo = SchoolYear::where('active', true)->first();

        if ($anoLetivoAtivo) {
            $query->where('id_schoolyear', $anoLetivoAtivo->id);
        } else {
            // Se não houver ano letivo ativo, retorna vazio para segurança
            $query->whereRaw('0 = 1');
        }

        return $query;
    }

    public static function form(Form $form): Form
    {

        return $form
            ->schema([

                Section::make('Dia / Hora')
                    ->collapsible()
                    ->description('Informe quando a aula será realizada')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('id_weekday')
                                    ->label('Dia da Semana')
                                    ->required()
                                    ->options(Weekday::all()->pluck('weekday', 'id'))
                                    ->placeholder('Selecione o dia da semana'),

                                Select::make('id_timeperiod')
                                    ->label('Hora de Início')
                                    ->required()
                                    ->placeholder('Selecione a hora de início')
                                    ->options(Timeperiod::all()->pluck('description', 'id'))
                                    ->reactive(),

                            ]),
                    ]),
                Section::make('Local da Aula')
                    ->collapsible()
                    ->description('Selecione o núcleo/pólo e a sala onde será dada a aula')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('id_building')
                                    ->label('Núcleo ou Pólo')
                                    ->required()
                                    ->options(Building::all()->pluck('address', 'id'))
                                    ->reactive()
                                    ->afterStateUpdated(fn (callable $set) => $set('id_room', null))
                                    ->placeholder('Selecione o local da aula')
                                    ->afterStateHydrated(function (callable $set, ?Schedule $record) {
                                        if ($record && $record->id_room && $record->room) {
                                            $set('id_building', $record->room->id_building);
                                        }
                                    }),

                                Select::make('id_room')
                                    ->label('Sala')
                                    ->required()
                                    ->disabled(fn (callable $get) => blank($get('id_building')))
                                    ->placeholder('Tem que preencher o Núcleo/Pólo')
                                    ->options(function (callable $get, ?Schedule $record) {
                                        $buildingId = $get('id_building') ?? $record?->room?->id_building;

                                        if (! $buildingId) {
                                            return [];
                                        }

                                        return Room::where('id_building', $buildingId)->pluck('name', 'id');
                                    })
                                    ->searchable()
                                    ->reactive()
                                    ->afterStateHydrated(function (callable $set, ?Schedule $record) {
                                        if ($record && $record->id_room) {
                                            $set('id_room', $record->id_room);
                                        }
                                    }),
                            ]),
                    ]),

                Section::make('Composição da Aula')
                    ->collapsible()
                    ->description('Defina a disciplina, turmas e alunos envolvidos')
                    ->schema([
                        Select::make('id_subject')
                            ->label('Disciplina')
                            ->required()
                            ->reactive()
                            ->searchable()
                            ->disabled(fn (callable $get) => blank($get('id_room')))
                            ->placeholder('Tem que preencher a Sala')
                            ->options(function () {
                                $userId = Auth::id();
                                $teacher = Teacher::where('id_user', $userId)->first();
                                if (! $teacher) {
                                    return collect(['' => 'Este utilizador não é um professor']);
                                }
                                $activeYear = SchoolYear::where('active', true)->first();
                                if (! $activeYear) {
                                    return collect(['' => 'Nenhum ano letivo ativo']);
                                }
                                $subjects = Subject::whereHas('teachers', function ($query) use ($teacher, $activeYear) {
                                    $query->where('id_teacher', $teacher->id)
                                        ->where('teacher_subjects.id_schoolyear', $activeYear->id);
                                })->pluck('name', 'id');

                                return $subjects->isEmpty()
                                    ? collect(['' => 'Nenhuma disciplina atribuída neste ano letivo'])
                                    : $subjects;
                            })

                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('id_subject', $state);
                                $set('id_classes', []);
                                $set('alunos', []);
                            }),

                        Select::make('id_classes')
                            ->label('Turmas')
                            ->disabled(fn (callable $get) => blank($get('id_subject')))
                            ->placeholder('Tem que preencher a disciplina primeiro')
                            ->multiple()
                            ->required(function (callable $get) {
                                $subjectId = $get('id_subject');
                                $subjectName = Subject::find($subjectId)?->name;

                                return ! in_array(strtolower($subjectName), ['reunião', 'tee']);
                            })
                            ->helperText('Selecione a(s) turma(s) que vão assistir à aula')
                            ->reactive()
                            ->afterStateHydrated(function (callable $set, ?Schedule $record) {
                                $set('id_classes', $record?->classes()->pluck('classes.id')->toArray());
                            })
                            ->options(function (callable $get) {
                                $subjectId = $get('id_subject');
                                $buildingId = $get('id_building');

                                if (! $subjectId) {
                                    return [];
                                }

                                $subject = Subject::find($subjectId);
                                if (! $subject) {
                                    return [];
                                }

                                $courseIds = $subject->courses()->pluck('courses.id');

                                return Classes::whereIn('id_course', $courseIds)
                                    ->whereHas('buildings', function ($query) use ($buildingId) {
                                        $query->when($buildingId, fn ($q) => $q->where('buildings.id', $buildingId));
                                    })
                                    ->pluck('name', 'id');
                            }),

                         Grid::make(1)
                             ->schema([
                                 Toggle::make('filter_last_year_students')
                                    ->label('Mostrar os meus alunos (último ano letivo)')
                                    ->default(true)
                                    ->reactive(),
                            ]),
                        TextInput::make('filter_student_name')
                            ->label('Filtrar por nome do aluno')
                            ->placeholder('Digite parte do nome...')
                            ->reactive(),

                        CheckboxList::make('students')
                            ->label('Alunos matriculados na disciplina')
                            ->helperText('Selecione os alunos que vão assistir à aula')
                            ->reactive()
                            ->afterStateHydrated(function (callable $set, ?Schedule $record) {
                                if ($record && $record->exists) {
                                    $studentIds = $record->students()->pluck('students.id')->filter()->values()->toArray();

                                    if (! empty($studentIds)) {
                                        $set('students', $studentIds);

                                        if (blank($record->shift)) {
                                            $set('shift', Student::whereIn('id', $studentIds)
                                                ->pluck('number')
                                                ->sort()
                                                ->implode(', '));
                                        }
                                    } else {
                                        $set('students', []);
                                    }
                                } else {
                                    $set('students', []);
                                }
                            })
                            ->afterStateUpdated(function ($state, callable $set) {
                                $studentIds = is_array($state) ? $state : [];

                                if (count($studentIds) > 0) {
                                    $numeros = Student::whereIn('id', $studentIds)
                                        ->pluck('number')
                                        ->sort()
                                        ->implode(', ');

                                    $set('shift', $numeros);

                                 } else {
                                    $set('shift', null);
                                }
                            })
                            ->columns(4)
                             ->options(function (callable $get, ?Schedule $record) {
                                 $subjectId = $get('id_subject');
                                 $schoolYear = SchoolYear::where('active', true)->first();
                                 $classIds = $get('id_classes') ?? [];
                                 $filtrarUltimoAno = $get('filter_last_year_students');
                                $filtroNome = trim($get('filter_student_name'));

                                 if (! $subjectId || ! $schoolYear) {
                                     return [];
                                 }

                                 if (empty($classIds) && $record?->exists) {
                                     $classIds = $record->classes()->pluck('classes.id')->all();
                                 }

                                $registrationIds = DB::table('registrations_subjects')
                                    ->where('id_subject', $subjectId)
                                    ->pluck('id_registration');

                                if ($registrationIds->isEmpty()) {
                                    return [];
                                }

                                 $query = Registration::with(['student', 'class'])
                                     ->whereIn('id', $registrationIds)
                                     ->where('id_schoolyear', $schoolYear->id);

                                 if (empty($classIds)) {
                                     return [];
                                 }

                                 $query->whereIn('id_class', $classIds);

                                if ($filtrarUltimoAno) {
                                     $professorId = Auth::user()?->teacher?->id;
                                     $anoLetivoAnterior = SchoolYear::query()
                                          ->where('schoolyear', '<', $schoolYear->schoolyear)
                                          ->orderByDesc('schoolyear')
                                         ->first();

                                    if (! $anoLetivoAnterior) {
                                        return [];
                                    }

                                    $scheduleIds = DB::table('schedules')
                                        ->where('id_teacher', $professorId)
                                        ->where('id_subject', $subjectId)
                                        ->where('id_schoolyear', $anoLetivoAnterior->id)
                                        ->pluck('id');

                                    $studentIdsPermitidos = DB::table('schedules_students')
                                        ->whereIn('id_schedule', $scheduleIds)
                                        ->pluck('id_student');

                                    if ($studentIdsPermitidos->isNotEmpty()) {
                                        $query->whereIn('id_student', $studentIdsPermitidos);
                                    } else {
                                        return [];
                                    }
                                }

                                // Aplica filtro por nome (se preenchido)
                                if (! empty($filtroNome)) {
                                    $query->whereHas('student', function ($q) use ($filtroNome) {
                                        $q->where('name', 'like', '%'.$filtroNome.'%');
                                    });
                                }

                                 return $query->orderBy('id_class')->orderBy('id')->get()
                                     ->filter(fn ($registration) => $registration->student)
                                     ->groupBy('id_student')
                                     ->mapWithKeys(function ($registrations, $studentId) {
                                         $registration = $registrations->first();
                                         $student = $registration->student;
                                         $class = $registration->class?->name ?? '—';

                                         return [
                                             $studentId => "{$student->number} - {$student->name} - {$class}",
                                         ];
                                     });
                             }),

                        //     Section::make('Turno')
                        //         ->collapsible()
                        //         ->description('Indique o turno da aula')
                        //         ->schema([
                        //             Select::make('shift')
                        //                 ->label('Turno')
                        //                 ->visible(function (callable $get) {
                        //                     $students = $get('students');
                        //                     return is_array($students) ? count($students) === 0 : true;
                        //                 })
                        //                 ->options(function () {
                        //                     $acronym = Auth::user()?->teacher?->acronym ?? '';
                        //                     return [
                        //                         "Turno A - $acronym" => "Turno A - $acronym",
                        //                         "Turno B - $acronym" => "Turno B - $acronym",
                        //                         "Turno C - $acronym" => "Turno C - $acronym",
                        //                         "Turno D - $acronym" => "Turno D - $acronym",
                        //                     ];
                        //                 })
                        //                 ->placeholder('Em caso de ser a turma toda, selecione o turno'),

                        //             TextInput::make('shift1')
                        //                 ->label('Turno Gerado (automático)')
                        //                 ->visible(function (callable $get) {
                        //                     $students = $get('students');
                        //                     return is_array($students) && count($students) > 0;
                        //                 })
                        //                 ->extraAttributes(['readonly' => true])
                        //                 ->default(fn(callable $get, ?Schedule $record) => $get('shift') ?? $record?->shift)
                        //                 ->placeholder('Será preenchido automaticamente com os números dos alunos'),
                        //         ]),

                        //     TextInput::make('shift_limit')
                        //         ->label('Número limite de alunos')
                        //         ->numeric()
                        //         ->minValue(1)
                        //         ->visible(function (callable $get) {
                        //             $shift = $get('shift');
                        //             return in_array($shift, [
                        //                 "Turno A - " . (Auth::user()?->teacher?->acronym ?? ''),
                        //                 "Turno B - " . (Auth::user()?->teacher?->acronym ?? ''),
                        //                 "Turno C - " . (Auth::user()?->teacher?->acronym ?? ''),
                        //                 "Turno D - " . (Auth::user()?->teacher?->acronym ?? ''),
                        //             ]);
                        //         }),

                        // ]),

                        Section::make('Turno')
                            ->collapsible()
                            ->description('Indique o turno da aula')
                            ->schema([
                                Select::make('shift')
                                    ->label('Turno')
                                    ->reactive() // <--- garante que mudanças disparam actualizações
                                    ->visible(fn (callable $get) => is_array($get('students')) ? count($get('students')) === 0 : true)
                                    ->options(function () {
                                        $acronym = Auth::user()?->teacher?->acronym ?? '';

                                        return [
                                            "Turno A - $acronym" => "Turno A - $acronym",
                                            "Turno B - $acronym" => "Turno B - $acronym",
                                            "Turno C - $acronym" => "Turno C - $acronym",
                                            "Turno D - $acronym" => "Turno D - $acronym",
                                        ];
                                    })
                                    ->placeholder('Em caso de ser a turma toda, não selecione o turno'),

                                Placeholder::make('generated_shift')
                                    ->label('Turno Gerado (automático)')
                                    ->visible(fn (callable $get) => is_array($get('students')) && count($get('students')) > 0)
                                    ->content(fn (callable $get, ?Schedule $record) => $get('shift') ?: $record?->shift ?: 'Será preenchido automaticamente com os números dos alunos'),

                                TextInput::make('shift_limit')
                                    ->label('Número limite de alunos')
                                    ->numeric()
                                    ->minValue(1)
                                    ->visible(
                                        fn (callable $get) => Str::startsWith($get('shift'), ['Turno A', 'Turno B', 'Turno C', 'Turno D'])
                                    ),
                            ]),

                    ]),

                ActionGroup::make([
                    Action::make('justificarConflito')
                        ->label('Solicitar Troca de Horário')
                        ->visible(fn ($livewire) => $livewire->conflictingSchedule !== null)
                        ->icon('heroicon-o-exclamation-triangle')
                        ->color('danger')
                        ->modalHeading('Pedido de Troca de Horário')
                        ->modalWidth('xl')
                        ->modalDescription('Por favor, forneça uma justificação para a troca de horário.')
                        ->modalSubmitActionLabel('Submeter Justificação')
                        ->modalCancelActionLabel('Cancelar')
                        ->form([
                            Textarea::make('justification')
                                ->label('Escreva a justificação')
                                ->required()
                                ->minLength(10),
                        ])
                        ->action(fn (array $data, $livewire) => $livewire->submitJustification($data)),
                ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('teacher.name')
                    ->label('Professor')
                    ->sortable()
                    ->toggleable()
                    ->searchable()
                    ->visible(fn () => UserHelper::isUserSuperAdmin())
                    ->wrap(),
                TextColumn::make('weekday.weekday')
                    ->label('Dia da Semana')
                    ->sortable()
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('timeperiod.description')
                    ->label('Hora da Aula')
                    ->sortable()
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('subject.name')
                    ->label('Disciplina')
                    ->wrap()
                    ->sortable()
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('classes.name')
                    ->label('Turma')
                    ->wrap()
                    ->sortable()
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('room.building.name')
                    ->label('Pólo')
                    ->sortable()
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('room.name')
                    ->label('Sala')
                    ->sortable()
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->sortable()
                    ->toggleable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Pendente' => 'warning',
                        'Aprovado' => 'success',
                        'Recusado' => 'danger',
                        'Escalado' => 'info',
                        'Aprovado DP' => 'success',
                        'Recusado DP' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),

            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'Pendente' => 'Pendente',
                        'Aprovado' => 'Aprovado',
                        'Recusado' => 'Recusado',
                        'Escalado' => 'Escalado',
                        'Aprovado DP' => 'Aprovado DP',
                        'Recusado DP' => 'Recusado DP',
                        // 'Eliminado' => 'Eliminado',
                    ]),

                SelectFilter::make('teacher_id')
                    ->label('Professor')
                    ->relationship('teacher', 'name')
                    ->searchable()
                    ->visible(fn () => UserHelper::isUserSuperAdmin()),

                SelectFilter::make('weekday_id')
                    ->label('Dia da Semana')
                    ->relationship('weekday', 'weekday'),

                SelectFilter::make('timeperiod_id')
                    ->label('Hora da Aula')
                    ->relationship('timeperiod', 'description'),

                SelectFilter::make('subject_id')
                    ->label('Disciplina')
                    ->relationship('subject', 'name'),

                SelectFilter::make('classes_id')
                    ->label('Turma')
                    ->relationship('classes', 'name'),

                SelectFilter::make('room_id')
                    ->label('Sala')
                    ->relationship('room', 'name'),

                SelectFilter::make('room.building_id')
                    ->label('Pólo')
                    ->relationship('room.building', 'name'),

                TernaryFilter::make('incluir_eliminados')
                    ->label('Incluir Eliminados')
                    ->placeholder('Ocultar Eliminados') // Valor nulo (default)
                    ->trueLabel('Mostrar Eliminados')
                    ->falseLabel('Ocultar Eliminados') // Mesmo que default
                    ->queries(
                        true: fn (Builder $query) => $query, // não aplica filtro → mostra todos
                        false: fn (Builder $query) => $query->where('status', '!=', 'Eliminado'),
                        blank: fn (Builder $query) => $query->where('status', '!=', 'Eliminado'),
                    ),

            ])
            ->headerActions([
                Actions\Action::make('exportar_selecionados')
                    ->label('Exportar Horários')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => self::exportSchedules())
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn () => Gate::allows('export', Schedule::class)),

            ])
            ->bulkActions([

                BulkAction::make('exportar_selecionados')
                    ->label('Exportar Selecionados')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (Collection $records) => self::exportSchedules($records))
                    ->visible(fn () => Gate::allows('export', Schedule::class)),
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
            'index' => Pages\ListSchedules::route('/'),
            'create' => Pages\CreateSchedule::route('/create'),
            'edit' => Pages\EditSchedule::route('/{record}/edit'),
        ];
    }

    public static function getRecordActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
