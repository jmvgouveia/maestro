<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RegistrationSubjectResource\RelationManagers;
use App\Models\RegistrationSubject;
use App\Models\Schedule;
use Filament\Facades\Filament;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\text;
use function Livewire\Volt\placeholder;

class RegistrationSubjectResource extends Resource
{
    protected static ?string $model = RegistrationSubject::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Aluno';

    protected static ?string $navigationLabel = 'Horário';

    public static function getLabel(): string
    {
        return 'Horário';
    }

    public static function getPluralLabel(): string
    {
        return 'Horário';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Aluno');
    }

    public static function form(Form $form): Form
    {
        return $form
            // schema([
            //     Section::make('Escolha o Turno')
            //         ->columns(1)
            //         ->schema(function ($record) {

            //             $availableShifts = \App\Models\Schedule::query()
            //                 ->where('id_subject', $record->id_subject)
            //                 ->whereHas('classes', fn($q) => $q->where('classes.id', $record->registration->id_class))
            //                 ->where('status', 'Aprovado')
            //                 ->where('id_schoolyear', $record->registration->id_schoolyear) //
            //                 ->where('shift', 'like', 'Turno%') // começa com "Turno"
            //                 // Exemplo de filtro adicional
            //                 ->get();

            //             $shiftCards = collect($availableShifts)->map(function ($s) {
            //                 $day = $s->weekday?->weekday ?? '';
            //                 $start = $s->timeperiod?->start_time ? \Carbon\Carbon::createFromFormat('H:i:s', $s->timeperiod->start_time)->format('H:i') : '';
            //                 $end   = $s->timeperiod?->end_time ? \Carbon\Carbon::createFromFormat('H:i:s', $s->timeperiod->end_time)->format('H:i') : '';
            //                 $room = $s->room?->name ?? '';
            //                 $inscritos = \App\Models\RegistrationSubject::where('shift', $s->id)
            //                     ->whereHas('registration', fn($q) => $q->where('id_class', $s->classes->pluck('id')))
            //                     ->count();
            //                 $vagas = max(0, $s->shift_limit - $inscritos);

            //                 return Section::make("👨‍🏫Professor: {$s->teacher?->name}")
            //                     ->extraAttributes([
            //                         'class' => 'bg-gray-50 border rounded-lg p-4 shadow mb-4 cursor-pointer hover:bg-blue-50 transition-colors'
            //                     ])
            //                     ->schema([
            //                         Placeholder::make("Turno {$s->shift}")
            //                             ->label('🎯 Turno')
            //                             ->content($s->shift ?? '-')
            //                             ->extraAttributes(['class' => 'font-semibold text-gray-800']),
            //                         Placeholder::make("day_{$s->id}")
            //                             ->label('📅 Dia')
            //                             ->content($day ?: ' - '),
            //                         Placeholder::make("hour_{$s->id}")
            //                             ->label('⏰ Horário')
            //                             ->content("{$start}–{$end}"),
            //                         Placeholder::make("room_{$s->id}")
            //                             ->label('🏫 Sala')
            //                             ->content($room ?: '-'),
            //                         Placeholder::make("vagas_{$s->id}")
            //                             ->label('👥 Vagas')
            //                             ->content($vagas),

            //                         ToggleButtons::make('shift')
            //                             ->label('Escolher')
            //                             ->options([$s->id => 'Selecionar'])
            //                             ->reactive()
            //                             ->visible(fn() => $vagas > 0)
            //                             ->dehydrated(fn() => $vagas > 0) // não envia o valor no submit quando não há vagas
            //                             ->afterStateHydrated(function ($state, callable $set) use ($vagas) {
            //                                 if ($vagas <= 0) {
            //                                     $set('shift', null); // limpa seleção antiga
            //                                 }
            //                             }),

            //                         ToggleButtons::make("sem_vagas_{$s->id}")
            //                             ->label('Escolher')
            //                             ->options(['sem' => 'Sem vagas'])        // um único “botão” com o texto
            //                             ->colors(['sem' => 'danger'])            // vermelho nativo do Filament
            //                             ->icons(['sem' => 'heroicon-m-no-symbol']) // ícone opcional
            //                             ->inline()                               // visual de “pill”
            //                             ->disabled()                             // não clicável
            //                             ->dehydrated(false)                      // não submete nada
            //                             ->visible(fn() => $vagas <= 0)
            //                             ->columnSpanFull(),         // só aparece quando não há vagas

            //                     ])

            //                     ->columns(5);
            //             })->toArray();

            //             // Adiciona card "Nenhum turno"
            //             $noneCard = Section::make("Nenhum turno")
            //                 ->extraAttributes([
            //                     'class' => 'bg-gray-50 border rounded-lg p-4 shadow mb-4 cursor-pointer hover:bg-red-50 transition-colors'
            //                 ])
            //                 ->schema([
            //                     ToggleButtons::make('shift')
            //                         ->label('Escolher')
            //                         ->options(['none' => 'Selecionar nenhum turno'])
            //                         ->reactive()
            //                 ]);

            //             return array_merge($shiftCards, [$noneCard]);
            //         }),
            // ]);
            ->schema(function ($record) {
                if (! $record) {
                    return [];
                }

                $availableShifts = Schedule::query()
                    ->where('id_subject', $record->id_subject)
                    ->whereHas('classes', fn ($q) => $q->where('classes.id', $record->registration->id_class))
                    ->where('status', 'Aprovado')
                    ->where('id_schoolyear', $record->registration->id_schoolyear)
                    ->where('shift', 'like', 'Turno%')
                    ->get();

                $grouped = $availableShifts->groupBy(function ($s) {
                    return $s->id_teacher.'|'.($s->shift ?? '');
                });

                $selectionOptions = [];

                $shiftCards = $grouped->map(function ($group) use ($record, &$selectionOptions) {
                    /** @var Schedule $first */
                    $first = $group->first();

                    $slotLines = [];      // linhas com dia/hora/sala (sem nº de vagas)
                    $scheduleIds = $group->pluck('id');
                    $limit = (int) $group->min('shift_limit');
                    $enrolled = RegistrationSubject::query()
                        ->whereIn('id_schedule', $scheduleIds)
                        ->whereKeyNot($record->getKey())
                        ->count();
                    $available = max(0, $limit - $enrolled);
                    $selectedScheduleId = $available > 0 ? $first->id : null;

                    foreach ($group as $s) {
                        $day = $s->weekday?->weekday ?? '';
                        $start = $s->timeperiod?->start_time ? \Carbon\Carbon::createFromFormat('H:i:s', $s->timeperiod->start_time)->format('H:i') : '';
                        $end = $s->timeperiod?->end_time ? \Carbon\Carbon::createFromFormat('H:i:s', $s->timeperiod->end_time)->format('H:i') : '';
                        $room = $s->room?->name ?? '';

                        // linha visual (sem nº de vagas)
                        $linha = trim(sprintf(
                            '%s às %s–%s%s',
                            $day ?: '-',
                            $start,
                            $end,
                            $room ? ', Sala: '.$room : '',
                        ));

                        $slotLines[] = Placeholder::make("slot_{$s->id}")
                            ->label(' ')
                            ->content($linha)
                            ->extraAttributes(['class' => 'schedule-slot']);
                    }

                    $temVagaNoTurno = $selectedScheduleId !== null;

                    if ($temVagaNoTurno) {
                        $selectionOptions[$selectedScheduleId] = trim(sprintf(
                            '%s · Prof. %s · %d %s',
                            $first->shift ?: 'Turno',
                            $first->teacher?->name ?: '—',
                            $available,
                            $available === 1 ? 'vaga' : 'vagas',
                        ));
                    }

                    return Section::make("Professor: {$first->teacher?->name}")
                        ->icon('heroicon-o-user')
                        ->compact()
                        ->extraAttributes([
                            'class' => 'schedule-shift-card mb-3',
                        ])
                        ->schema([
                            Placeholder::make("resumo_turno_{$first->id}")
                                ->label(false)
                                ->content(new HtmlString(sprintf(
                                    '<div class="schedule-shift-summary-inner"><span><b>TURNO</b><strong>%s</strong></span><span><b>VAGAS DISPONÍVEIS</b><strong>%d de %d</strong></span></div>',
                                    e($first->shift ?: '—'),
                                    $available,
                                    $limit,
                                )))
                                ->extraAttributes(['class' => 'schedule-shift-summary'])
                                ->columnSpanFull(),

                            // Vagas do turno completo, não de cada linha de horário.
                            // Lista de horários (sem nº de vagas por linha)
                            Fieldset::make('Horários:')
                                ->schema($slotLines)
                                ->columns(1)
                                ->columnSpanFull()
                                ->extraAttributes(['class' => 'schedule-slots']),

                            // Sem vagas no turno
                            ToggleButtons::make("sem_vagas_{$first->id}")
                                ->label('Escolher')
                                ->options(['sem' => 'Sem vagas'])
                                ->colors(['sem' => 'danger'])
                                ->icons(['sem' => 'heroicon-m-no-symbol'])
                                ->inline()
                                ->disabled()
                                ->dehydrated(false)
                                ->visible(fn () => ! $temVagaNoTurno)
                                ->columnSpanFull(),
                        ])
                        ->columns(1);
                })->values()->toArray();

                $selectionOptions['none'] = 'Nenhum turno';

                $selection = Section::make('Escolher turno')
                    ->icon('heroicon-o-check-circle')
                    ->compact()
                    ->extraAttributes(['class' => 'schedule-selection'])
                    ->schema([
                        ToggleButtons::make('id_schedule')
                            ->hiddenLabel()
                            ->options($selectionOptions)
                            ->reactive()
                            ->required()
                            ->columns(1),
                    ]);

                return array_merge($shiftCards, [$selection]);
            });
    }

    // public static function table(Table $table): Table
    // {

    //     return $table

    //         ->columns([

    //             TextColumn::make('subject.name')->label('Disciplina'),

    //             TextColumn::make('turno_display')
    //                 ->label('Horário')
    //                 ->badge()
    //                 ->extraAttributes(['style' => 'white-space: pre-line;']) // permite \n na descrição
    //                 ->state(function ($record) {
    //                     // selectedSchedule existe?
    //                     $hasSelected = method_exists($record, 'selectedSchedule')
    //                         ? $record->selectedSchedule()->exists()
    //                         : (bool) $record->selectedSchedule;

    //                     $selected = $record->selectedSchedule;

    //                     // 1) Se houver selectedSchedule, mostra o nome do docente (se existir)
    //                     if ($hasSelected) {
    //                         $name =  $selected?->teacher?->name;
    //                         if (! blank($name)) {
    //                             return 'Prof. ' . $name;
    //                         }
    //                         // Se não tiver docente, tenta pelo menos o turno; senão mostra “Turno por escolher”
    //                         return blank($selected?->shift) ? 'Turno por escolher' : ($selected->shift);
    //                     }

    //                     // 2) Fallback: procurar no Schedule pelo nº do aluno dentro de 'shift'
    //                     $studentNo = $record->student?->number
    //                         ?? $record->registration?->student?->number
    //                         ?? $record->number
    //                         ?? null;

    //                     if (! $studentNo) {
    //                         return 'Sem Turno';
    //                     }

    //                     $candidates = Schedule::query()
    //                         ->where('id_subject', $record->id_subject)
    //                         ->where('status', 'Aprovado')
    //                         ->where('shift', 'like', '%' . $studentNo . '%')
    //                         ->when(
    //                             $record->registration?->id_class,
    //                             fn($q, $id) =>
    //                             $q->whereHas('classes', fn($qq) => $qq->where('classes.id', $id))
    //                         )
    //                         ->with(['weekday', 'timeperiod', 'room', 'teacher'])
    //                         ->get()
    //                         // evitar falso match (ex.: 444 em 4444)
    //                         ->filter(fn($sch) => preg_match('/(^|\D)' . preg_quote($studentNo, '/') . '(\D|$)/', (string) $sch->shift))
    //                         ->values();

    //                     if ($candidates->isEmpty()) {
    //                         return 'Sem Turno';
    //                     }

    //                     // guarda para a description/cor
    //                     $record->foundSchedulesForTurno = $candidates;

    //                     // Badge: nomes dos docentes (até 2 + “+N”)
    //                     $names = $candidates->pluck('teacher.name')->filter()->unique()->values();

    //                     if ($names->isEmpty()) {
    //                         // fallback: mostra o turno do primeiro
    //                         return (string) ($candidates->first()->shift ?? 'Sem Turno');
    //                     }

    //                     return $names->count() <= 2
    //                         ? 'Prof. ' . $names->implode(' / ')
    //                         : $names->take(2)->implode(' / ') . ' +' . ($names->count() - 2);
    //                 })
    //                 ->color(function ($record) {
    //                     $hasSelected = method_exists($record, 'selectedSchedule')
    //                         ? $record->selectedSchedule()->exists()
    //                         : (bool) $record->selectedSchedule;

    //                     if ($hasSelected && ! blank($record->selectedSchedule?->shift)) {
    //                         return 'success';
    //                     }

    //                     if (isset($record->foundSchedulesForTurno) && $record->foundSchedulesForTurno->isNotEmpty()) {
    //                         return 'success';
    //                     }

    //                     return $hasSelected ? 'warning' : 'gray';
    //                 })
    //                 ->description(function ($record) {
    //                     $fmt = fn($t) => $t ? substr($t, 0, 5) : null; // HH:MM

    //                     // nº de aluno do registo (ajusta a origem se diferente)
    //                     $studentNo = $record->student?->number
    //                         ?? $record->registration?->student?->number
    //                         ?? $record->number
    //                         ?? null;

    //                     // helper: gera uma linha com as regras de Individual/Partilhada
    //                     $lineFor = function ($s) use ($fmt, $studentNo) {
    //                         $turno = (string) ($s->shift ?? '');

    //                         // Extrair todos os números do shift (na ordem, únicos)
    //                         $nums = collect();
    //                         if ($turno !== '') {
    //                             preg_match_all('/\d+/', $turno, $m);
    //                             $nums = collect($m[0])->map(fn($n) => (string) $n)->unique()->values();
    //                         }

    //                         $isIndividual = $studentNo && $nums->count() === 1 && $nums->first() === (string) $studentNo;

    //                         // Construir pedaços base
    //                         $dia  = $s->weekday?->weekday ?: '—';
    //                         $hora = ($fmt($s->timeperiod?->start_time) && $fmt($s->timeperiod?->end_time))
    //                             ? $fmt($s->timeperiod?->start_time) . '–' . $fmt($s->timeperiod?->end_time)
    //                             : '—';
    //                         $sala = $s->room?->name ?: '—';

    //                         // Sufixos "Aula Individual / Partilhada"
    //                         $suffix = '';
    //                         if ($studentNo && $nums->isNotEmpty()) {
    //                             $others = $nums->filter(fn($n) => $n !== (string) $studentNo)->values();
    //                             if ($others->isEmpty()) {
    //                                 $suffix = ' (Aula Individual)';
    //                             } else {
    //                                 $lista = $others->implode(', ');
    //                                 $suffix = ' (Aula Partilhada com ' . ($others->count() === 1 ? 'Aluno nº ' : 'Alunos nº ') . $lista . ')';
    //                             }
    //                         }

    //                         // Se for Individual: não mostrar "Turno …"
    //                         if ($isIndividual) {
    //                             return "{$dia} ● {$hora} ● {$sala}{$suffix}";
    //                         }

    //                         // Caso normal (ou partilhada): inclui Turno
    //                         $turnoShow = (stripos($turno, 'Turno') === 0) ? $turno . '  ● ' : '';

    //                         return "{$turnoShow} {$dia}  ●  {$hora}  ● {$sala}{$suffix}";
    //                     };

    //                     // 1) selectedSchedule → 1 linha
    //                     if ($record->selectedSchedule) {
    //                         return $lineFor($record->selectedSchedule);
    //                     }

    //                     // 2) fallback → TODAS as marcações (já guardadas em foundSchedulesForTurno)
    //                     if (isset($record->foundSchedulesForTurno) && $record->foundSchedulesForTurno->isNotEmpty()) {
    //                         return $record->foundSchedulesForTurno->map(fn($s) => $lineFor($s))->implode("\n");
    //                     }

    //                     return '';
    //                 })
    //                 ->extraAttributes(['style' => 'white-space: pre-line;']) // para \n virar múltiplas linhas
    //                 ->sortable(false)
    //                 ->searchable(false),

    //             //----

    //         ])->actions([
    //             // Tables\Actions\EditAction::make('selectTurno')
    //             //     ->label('Selecionar Turno')
    //             //     ->visible(fn($record) => (bool) $record->subject?->student_can_enroll),

    //             // 1) Selecionar Turno — só quando pode inscrever e a janela está aberta
    //             Tables\Actions\EditAction::make('selectTurno')
    //                 ->label('Selecionar Turno')
    //                 ->visible(function ($record) {
    //                     $canEnroll = (bool) $record->subject?->student_can_enroll;
    //                     $sy = $record->registration?->schoolyear;

    //                     if (! $canEnroll || ! $sy || ! $sy->active) {
    //                         return false;
    //                     }

    //                     $now    = Carbon::now()->startOfDay();
    //                     $start  = $sy->start_date_registration
    //                         ? Carbon::parse($sy->start_date_registration)->startOfDay() : null;
    //                     $end    = $sy->end_date_registration
    //                         ? Carbon::parse($sy->end_date_registration)->endOfDay() : null;

    //                     $open = ($start && $now->greaterThanOrEqualTo($start))
    //                         && (is_null($end) || $now->lessThanOrEqualTo($end));

    //                     return $open;
    //                 }),

    //             // 2) Período de inscrição — só quando pode inscrever MAS a janela NÃO está aberta
    //             Action::make('verPeriodo')
    //                 ->label('Período de inscrição')
    //                 ->icon('heroicon-m-information-circle')
    //                 ->color(function ($record) {
    //                     // amarelo se ainda não abriu; vermelho se já terminou / sem datas
    //                     $sy = $record->registration?->schoolyear;
    //                     if (! $sy) return 'danger';

    //                     $now   = Carbon::now()->startOfDay();
    //                     $start = $sy->start_date_registration
    //                         ? Carbon::parse($sy->start_date_registration)->startOfDay() : null;
    //                     $end   = $sy->end_date_registration
    //                         ? Carbon::parse($sy->end_date_registration)->endOfDay() : null;

    //                     if ($start && $now->lt($start)) return 'warning';
    //                     return 'danger';
    //                 })
    //                 ->visible(function ($record) {
    //                     $canEnroll = (bool) $record->subject?->student_can_enroll;
    //                     $sy = $record->registration?->schoolyear;

    //                     if (! $canEnroll || ! $sy || ! $sy->active) {
    //                         return false;
    //                     }

    //                     $now    = Carbon::now()->startOfDay();
    //                     $start  = $sy->start_date_registration
    //                         ? Carbon::parse($sy->start_date_registration)->startOfDay() : null;
    //                     $end    = $sy->end_date_registration
    //                         ? Carbon::parse($sy->end_date_registration)->endOfDay() : null;

    //                     $open = ($start && $now->greaterThanOrEqualTo($start))
    //                         && (is_null($end) || $now->lessThanOrEqualTo($end));

    //                     // mostra esta ação apenas quando a janela NÃO está aberta
    //                     return ! $open;
    //                 })
    //                 ->modalHeading('Período de inscrição indisponível')
    //                 ->modalIcon('heroicon-m-no-symbol')
    //                 ->modalDescription(function ($record) {
    //                     $sy = $record->registration?->schoolyear;

    //                     if (! $sy || ! $sy->active) {
    //                         return "Não se encontra período de inscrição ativo.";
    //                     }

    //                     $now   = Carbon::now()->startOfDay();
    //                     $start = $sy->start_date_registration
    //                         ? Carbon::parse($sy->start_date_registration)->startOfDay() : null;
    //                     $end   = $sy->end_date_registration
    //                         ? Carbon::parse($sy->end_date_registration)->endOfDay() : null;

    //                     $startStr = $start ? $start->format('d/m/Y') : '—';
    //                     $endStr   = $end   ? $end->format('d/m/Y')   : '—';

    //                     if ($start && $now->lt($start)) {
    //                         return "Não se encontra período de inscrição ativo.\n"
    //                             . "Janela definida: {$startStr} a {$endStr}.\n"
    //                             . "Abre em {$start->diffForHumans($now, true)}.";
    //                     }

    //                     if ($end && $now->gt($end)) {
    //                         return "Não se encontra período de inscrição ativo.\n"
    //                             . "Janela decorreu de {$startStr} a {$endStr}.\n"
    //                             . "Terminou há {$end->diffForHumans($now, true)}.";
    //                     }

    //                     // Sem datas válidas configuradas
    //                     return "Não se encontra período de inscrição ativo.";
    //                 })
    //                 ->modalSubmitAction(false), // modal apenas informativo
    //         ])
    //     ;
    // }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject.name')
                    ->label('Disciplina')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('turno_display')
                    ->label('Horário')
                    ->badge()
                    ->extraAttributes(['style' => 'white-space: pre-line;']) // permite \n virar múltiplas linhas
                    ->state(function ($record) {
                        // 1) Tentar obter o nome do turno a partir do selectedSchedule
                        $selected = method_exists($record, 'selectedSchedule')
                            ? $record->selectedSchedule()->with(['teacher'])->first()
                            : $record->selectedSchedule;

                        $shiftName = null;

                        if ($selected) {
                            $shiftName = (string) ($selected->shift ?? null);

                            // Carrega TODAS as slots do mesmo turno (mesma disciplina + mesmo nome do turno)
                            $classId = $record->registration?->id_class;
                            $schoolYearId = $record->registration?->id_schoolyear;

                            $siblings = Schedule::query()
                                ->where('id_subject', $record->id_subject)
                                ->where('status', 'Aprovado')
                                ->when($shiftName, fn ($q) => $q->where('shift', $shiftName))
                                ->when(
                                    $classId,
                                    fn ($q) => $q->whereHas('classes', fn ($qq) => $qq->where('classes.id', $classId))
                                )
                                ->when(
                                    $schoolYearId,
                                    fn ($q) => $q->where('id_schoolyear', $schoolYearId)
                                )
                                ->with(['weekday', 'timeperiod', 'room', 'teacher', 'students'])
                                ->get();

                            $record->foundSchedulesForTurno = $siblings;

                            // Título do badge
                            $name = $selected->teacher?->name;
                            if (! blank($name)) {
                                return 'Prof. '.$name;
                            }

                            return $shiftName ?: 'Turno por escolher';
                        }

                        // 2) Fallback: tentar inferir o turno pelos candidatos (nº do aluno no texto do shift)
                        $studentNo = $record->student?->number
                            ?? $record->registration?->student?->number
                            ?? $record->number
                            ?? null;

                        if (! $studentNo) {
                            return 'Sem Turno';
                        }

                        $candidates = Schedule::query()
                            ->where('id_subject', $record->id_subject)
                            ->where('status', 'Aprovado')
                            ->where('id_schoolyear', $record->registration?->id_schoolyear)
                            ->whereHas('students', fn ($q) => $q->where('students.id', $record->registration?->id_student))
                            ->when(
                                $record->registration?->id_class,
                                fn ($q, $id) => $q->whereHas('classes', fn ($qq) => $qq->where('classes.id', $id))
                            )
                            ->when(
                                $record->registration?->id_schoolyear,
                                fn ($q, $sy) => $q->where('id_schoolyear', $sy)
                            )
                            ->with(['weekday', 'timeperiod', 'room', 'teacher', 'students'])
                            ->get();

                        if ($candidates->isEmpty()) {
                            return 'Sem Turno';
                        }

                        // Usar o nome do turno do primeiro candidato para ir buscar TODAS as slots do mesmo turno
                        $shiftName = (string) ($candidates->first()->shift ?? null);
                        $classId = $record->registration?->id_class;
                        $schoolYearId = $record->registration?->id_schoolyear;

                        $siblings = Schedule::query()
                            ->where('id_subject', $record->id_subject)
                            ->where('status', 'Aprovado')
                            ->when($shiftName, fn ($q) => $q->where('shift', $shiftName))
                            ->when(
                                $classId,
                                fn ($q) => $q->whereHas('classes', fn ($qq) => $qq->where('classes.id', $classId))
                            )
                            ->when(
                                $schoolYearId,
                                fn ($q) => $q->where('id_schoolyear', $schoolYearId)
                            )
                            ->with(['weekday', 'timeperiod', 'room', 'teacher', 'students'])
                            ->get();

                        $record->foundSchedulesForTurno = $siblings;

                        // Badge: nomes dos docentes (até 2 + “+N”); se vazio, mostra o turno
                        $names = $siblings->pluck('teacher.name')->filter()->unique()->values();
                        if ($names->isEmpty()) {
                            return $shiftName ?: 'Sem Turno';
                        }

                        return $names->count() <= 2
                            ? 'Prof. '.$names->implode(' / ')
                            : $names->take(2)->implode(' / ').' +'.($names->count() - 2);
                    })
                    ->color(function ($record) {
                        $hasSelected = method_exists($record, 'selectedSchedule')
                            ? $record->selectedSchedule()->exists()
                            : (bool) $record->selectedSchedule;

                        if ($hasSelected && ! blank($record->selectedSchedule?->shift)) {
                            return 'success';
                        }

                        if (isset($record->foundSchedulesForTurno) && $record->foundSchedulesForTurno->isNotEmpty()) {
                            return 'success';
                        }

                        return $hasSelected ? 'warning' : 'gray';
                    })
                    ->description(function ($record) {
                        $fmt = fn ($t) => $t ? substr($t, 0, 5) : null; // HH:MM

                        $studentNo = $record->student?->number
                            ?? $record->registration?->student?->number
                            ?? $record->number
                            ?? null;

                        $lineFor = function ($s) use ($fmt, $studentNo) {
                            $turno = (string) ($s->shift ?? '');

                            $nums = $s->students
                                ? $s->students->pluck('number')->map(fn ($n) => (string) $n)->unique()->values()
                                : collect();
                            $isIndividual = $studentNo && $nums->count() === 1 && $nums->first() === (string) $studentNo;

                            $dia = $s->weekday?->weekday ?: '—';
                            $hora = ($fmt($s->timeperiod?->start_time) && $fmt($s->timeperiod?->end_time))
                                ? $fmt($s->timeperiod?->start_time).'–'.$fmt($s->timeperiod?->end_time)
                                : '—';
                            $sala = $s->room?->name ?: '—';

                            $suffix = '';
                            if ($studentNo && $nums->isNotEmpty()) {
                                $others = $nums->filter(fn ($n) => $n !== (string) $studentNo)->values();
                                if ($others->isEmpty()) {
                                    $suffix = ' (Aula Individual)';
                                } else {
                                    $lista = $others->implode(', ');
                                    $suffix = ' (Aula Partilhada com '.($others->count() === 1 ? 'Aluno nº ' : 'Alunos nº ').$lista.')';
                                }
                            }

                            // Não repetir "Turno ..." em cada linha — só dia/hora/sala
                            return "{$dia}  ●  {$hora}  ●  {$sala}{$suffix}";
                        };

                        // Mostrar TODAS as slots (se já foram carregadas)
                        if (isset($record->foundSchedulesForTurno) && $record->foundSchedulesForTurno->isNotEmpty()) {
                            return $record->foundSchedulesForTurno
                                ->map(fn ($s) => $lineFor($s))
                                ->unique()
                                ->values()
                                ->implode("\n");
                        }

                        // Último recurso: nada encontrado
                        return '';
                    })
                    ->sortable(false)
                    ->searchable(false),
            ])
            ->actions([
                // 1) Selecionar Turno — visível quando pode inscrever e janela aberta
                Tables\Actions\EditAction::make('selectTurno')
                    ->label('Selecionar Turno')
                    ->modalWidth('2xl')
                    ->extraModalWindowAttributes(['class' => 'maestro-schedule-modal'])
                    ->using(function (RegistrationSubject $record, array $data): void {
                        DB::transaction(function () use ($record, $data): void {
                            $schoolYear = $record->registration?->schoolyear;
                            $now = Carbon::now()->startOfDay();
                            $start = $schoolYear?->start_date_registration
                                ? Carbon::parse($schoolYear->start_date_registration)->startOfDay()
                                : null;
                            $end = $schoolYear?->end_date_registration
                                ? Carbon::parse($schoolYear->end_date_registration)->endOfDay()
                                : null;

                            $canEnroll = $record->subject?->student_can_enroll
                                && $schoolYear?->active
                                && $start
                                && $now->greaterThanOrEqualTo($start)
                                && (is_null($end) || $now->lessThanOrEqualTo($end));

                            if (! $canEnroll) {
                                throw ValidationException::withMessages([
                                    'id_schedule' => 'A seleção de turnos não está disponível neste momento.',
                                ]);
                            }

                            $scheduleId = $data['id_schedule'] ?? null;

                            if ($scheduleId === 'none') {
                                $record->update(['id_schedule' => null, 'shift' => null]);

                                return;
                            }

                            $schedule = Schedule::query()
                                ->whereKey($scheduleId)
                                ->where('id_subject', $record->id_subject)
                                ->where('id_schoolyear', $record->registration->id_schoolyear)
                                ->where('status', 'Aprovado')
                                ->where('shift', 'like', 'Turno%')
                                ->whereHas('classes', fn ($query) => $query->where('classes.id', $record->registration->id_class))
                                ->lockForUpdate()
                                ->first();

                            if (! $schedule) {
                                throw ValidationException::withMessages([
                                    'id_schedule' => 'O turno selecionado não é válido para esta inscrição.',
                                ]);
                            }

                            $turnoSchedules = Schedule::query()
                                ->where('id_subject', $record->id_subject)
                                ->where('id_schoolyear', $record->registration->id_schoolyear)
                                ->where('id_teacher', $schedule->id_teacher)
                                ->where('status', 'Aprovado')
                                ->where('shift', $schedule->shift)
                                ->whereHas('classes', fn ($query) => $query->where('classes.id', $record->registration->id_class))
                                ->lockForUpdate()
                                ->get(['id', 'shift_limit']);

                            $enrolled = RegistrationSubject::query()
                                ->whereIn('id_schedule', $turnoSchedules->pluck('id'))
                                ->whereKeyNot($record->getKey())
                                ->count();
                            $limit = (int) $turnoSchedules->min('shift_limit');

                            if ($enrolled >= $limit) {
                                throw ValidationException::withMessages([
                                    'id_schedule' => 'O turno selecionado já não tem vagas disponíveis.',
                                ]);
                            }

                            $record->update([
                                'id_schedule' => $schedule->getKey(),
                                'shift' => $schedule->shift,
                            ]);
                        });
                    })
                    ->visible(function ($record) {
                        $canEnroll = (bool) $record->subject?->student_can_enroll;
                        $sy = $record->registration?->schoolyear;

                        if (! $canEnroll || ! $sy || ! $sy->active) {
                            return false;
                        }

                        $now = \Carbon\Carbon::now()->startOfDay();
                        $start = $sy->start_date_registration
                            ? \Carbon\Carbon::parse($sy->start_date_registration)->startOfDay() : null;
                        $end = $sy->end_date_registration
                            ? \Carbon\Carbon::parse($sy->end_date_registration)->endOfDay() : null;

                        $open = ($start && $now->greaterThanOrEqualTo($start))
                            && (is_null($end) || $now->lessThanOrEqualTo($end));

                        return $open;
                    }),

                // 2) Período de inscrição — visível quando pode inscrever MAS janela NÃO está aberta
                Action::make('verPeriodo')
                    ->label('Período de inscrição')
                    ->icon('heroicon-m-information-circle')
                    ->color(function ($record) {
                        $sy = $record->registration?->schoolyear;
                        if (! $sy) {
                            return 'danger';
                        }

                        $now = \Carbon\Carbon::now()->startOfDay();
                        $start = $sy->start_date_registration
                            ? \Carbon\Carbon::parse($sy->start_date_registration)->startOfDay() : null;
                        $end = $sy->end_date_registration
                            ? \Carbon\Carbon::parse($sy->end_date_registration)->endOfDay() : null;

                        if ($start && $now->lt($start)) {
                            return 'warning';
                        }

                        return 'danger';
                    })
                    ->visible(function ($record) {
                        $canEnroll = (bool) $record->subject?->student_can_enroll;
                        $sy = $record->registration?->schoolyear;

                        if (! $canEnroll || ! $sy || ! $sy->active) {
                            return false;
                        }

                        $now = \Carbon\Carbon::now()->startOfDay();
                        $start = $sy->start_date_registration
                            ? \Carbon\Carbon::parse($sy->start_date_registration)->startOfDay() : null;
                        $end = $sy->end_date_registration
                            ? \Carbon\Carbon::parse($sy->end_date_registration)->endOfDay() : null;

                        $open = ($start && $now->greaterThanOrEqualTo($start))
                            && (is_null($end) || $now->lessThanOrEqualTo($end));

                        return ! $open; // só quando a janela NÃO está aberta
                    })
                    ->modalHeading('Período de inscrição indisponível')
                    ->modalIcon('heroicon-m-no-symbol')
                    ->modalDescription(function ($record) {
                        $sy = $record->registration?->schoolyear;

                        if (! $sy || ! $sy->active) {
                            return 'Não se encontra período de inscrição ativo.';
                        }

                        $now = \Carbon\Carbon::now()->startOfDay();
                        $start = $sy->start_date_registration
                            ? \Carbon\Carbon::parse($sy->start_date_registration)->startOfDay() : null;
                        $end = $sy->end_date_registration
                            ? \Carbon\Carbon::parse($sy->end_date_registration)->endOfDay() : null;

                        $startStr = $start ? $start->format('d/m/Y') : '—';
                        $endStr = $end ? $end->format('d/m/Y') : '—';

                        if ($start && $now->lt($start)) {
                            return "Não se encontra período de inscrição ativo.\n"
                                ."Janela definida: {$startStr} a {$endStr}.\n"
                                .'Abre em '.$start->diffForHumans($now, true).'.';
                        }

                        if ($end && $now->gt($end)) {
                            return "Não se encontra período de inscrição ativo.\n"
                                ."Janela decorreu de {$startStr} a {$endStr}.\n"
                                .'Terminou há '.$end->diffForHumans($now, true).'.';
                        }

                        return 'Não se encontra período de inscrição ativo.';
                    })
                    ->modalSubmitAction(false),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas(
                'registration.student',
                fn ($q) => $q->where('user_id', Auth::id())
            )
            ->whereHas(
                'registration.schoolyear',
                fn ($q) => $q->where('active', true)
            );
    }

    public static function getRelations(): array
    {
        return [
            // RelationManagers\SchedulesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => RegistrationSubjectResource\Pages\ListRegistrationSubjects::route('/'),
            //    'edit' => RegistrationSubjectResource\Pages\EditRegistrationSubject::route('/{record}/edit'),

            // 'edit' => Pages\EditRegistration::route('/{record}/edit'),
        ];
    }
}
