<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RegistrationSubjectResource\RelationManagers;
use App\Models\RegistrationSubject;
use App\Models\Registration;
use App\Models\Schedule;
use Filament\Facades\Filament;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
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
        return auth()->check()
            && (auth()->user()->hasRole('Aluno') || auth()->user()->isGuardian());
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
                    ->whereIn('status', ['Aprovado', 'Aprovado DP'])
                    ->where('id_schoolyear', $record->registration->id_schoolyear)
                    ->where('shift', 'like', 'Turno%')
                    ->get();

                $grouped = $availableShifts->groupBy(function ($s) {
                    return $s->id_teacher.'|'.($s->shift ?? '');
                });

                $selectionOptions = [];
                $selectionDescriptions = [];
                $unavailableCards = [];

                $grouped->each(function ($group) use ($record, &$selectionOptions, &$selectionDescriptions, &$unavailableCards): void {
                    /** @var Schedule $first */
                    $first = $group->first();

                    $scheduleIds = $group->pluck('id');
                    $limit = (int) $group->min('shift_limit');
                    $enrolled = self::countEnrolledForShift($record, $scheduleIds, (string) $first->shift);
                    $available = max(0, $limit - $enrolled);
                    $selectedScheduleId = $available > 0 ? $first->id : null;

                    $slotRows = $group
                        ->map(fn ($s): array => [
                            'day' => $s->weekday?->weekday ?? '',
                            'start' => $s->timeperiod?->start_time ?? '',
                            'end' => $s->timeperiod?->end_time ?? '',
                            'room' => $s->room?->name ?? '',
                        ])
                        ->sortBy('start')
                        ->values();

                    $mergedSlots = [];
                    foreach ($slotRows as $slot) {
                        $lastKey = array_key_last($mergedSlots);

                        if (
                            $lastKey !== null
                            && $mergedSlots[$lastKey]['day'] === $slot['day']
                            && $mergedSlots[$lastKey]['room'] === $slot['room']
                            && $mergedSlots[$lastKey]['end'] === $slot['start']
                        ) {
                            $mergedSlots[$lastKey]['end'] = $slot['end'];
                        } else {
                            $mergedSlots[] = $slot;
                        }
                    }

                    $slotLines = collect($mergedSlots)
                        ->map(fn (array $slot): string => trim(sprintf(
                            '%s · %s–%s%s',
                            $slot['day'] ?: '-',
                            substr($slot['start'], 0, 5),
                            substr($slot['end'], 0, 5),
                            $slot['room'] ? ', '.$slot['room'] : '',
                        )))
                        ->all();

                    $temVagaNoTurno = $selectedScheduleId !== null;

                    $slotSummary = collect($slotLines)
                        ->map(fn (string $line): string => e($line))
                        ->implode(' <span class="text-gray-400">·</span> ');
                    $availability = $temVagaNoTurno
                        ? "{$available} vagas disponíveis de {$limit}"
                        : 'Vagas preenchidas';

                    if ($temVagaNoTurno) {
                        $selectionOptions[$selectedScheduleId] = $first->teacher?->name ?: 'Professor a designar';
                        $selectionDescriptions[$selectedScheduleId] = new HtmlString(sprintf(
                            '<span class="turno-option-meta"><b>%s</b> <span>·</span> <b>%s</b></span><span class="turno-option-slots">%s</span>',
                            e($first->shift ?: 'Turno'),
                            e($availability),
                            $slotSummary,
                        ));

                        return;
                    }

                    $unavailableCards[] = Section::make($first->teacher?->name ?: 'Professor a designar')
                        ->icon('heroicon-o-user')
                        ->compact()
                        ->extraAttributes(['class' => 'schedule-shift-card schedule-shift-card-unavailable mb-3'])
                        ->schema([
                            Placeholder::make("resumo_turno_{$first->id}")
                                ->label('Turno')
                                ->content($first->shift ?: 'Turno'),
                            Placeholder::make("horarios_{$first->id}")
                                ->label('Horários')
                                ->content(new HtmlString($slotSummary)),
                            Placeholder::make("vagas_{$first->id}")
                                ->label('Disponibilidade')
                                ->content($availability),
                        ])
                        ->columns(3);
                });

                $selectionOptions['none'] = 'Não selecionar turno';
                $selectionDescriptions['none'] = 'Pode continuar sem um turno selecionado.';

                $selection = Section::make('Escolher turno')
                    ->icon('heroicon-o-check-circle')
                    ->compact()
                    ->extraAttributes(['class' => 'schedule-selection'])
                    ->schema([
                        Radio::make('id_schedule')
                            ->hiddenLabel()
                            ->options($selectionOptions)
                            ->descriptions($selectionDescriptions)
                            ->required()
                            ->columns(1)
                            ->extraAttributes(['class' => 'turno-selection-options']),
                    ]);

                return array_merge([$selection], $unavailableCards);
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
            ->heading(fn (): string => self::activeStudentContext())
            ->columns([
                TextColumn::make('subject.name')
                    ->label('Disciplina')
                    ->sortable()
                    ->searchable()
                    ->description(fn ($record): string => $record->registration?->class?->name
                        ? 'Turma: '.$record->registration->class->name
                        : 'Turma não definida'),

                TextColumn::make('turno_display')
                    ->label('Horário')
                    ->badge()
                    ->extraAttributes(['style' => 'white-space: pre-line;']) // permite \n virar múltiplas linhas
                    ->state(function ($record) {
                        $selected = method_exists($record, 'selectedSchedule')
                            ? $record->selectedSchedule()->with(['teacher'])->first()
                            : $record->selectedSchedule;

                        $shiftName = null;

                        if ($selected) {
                            $shiftName = (string) ($selected->shift ?? null);
                            $classId = $record->registration?->id_class;
                            $schoolYearId = $record->registration?->id_schoolyear;

                            $siblings = Schedule::query()
                                ->where('id_subject', $record->id_subject)
                                ->where('id_teacher', $selected->id_teacher)
                                ->whereIn('status', ['Aprovado', 'Aprovado DP'])
                                ->when($shiftName, fn ($q) => $q->where('shift', $shiftName))
                                ->when($classId, fn ($q) => $q->whereHas('classes', fn ($qq) => $qq->where('classes.id', $classId)))
                                ->when($schoolYearId, fn ($q) => $q->where('id_schoolyear', $schoolYearId))
                                ->with(['weekday', 'timeperiod', 'room', 'teacher', 'students'])
                                ->get();

                            $record->foundSchedulesForTurno = $siblings;

                            return $selected->teacher?->name
                                ? 'Prof. '.$selected->teacher->name
                                : ($shiftName ?: 'Turno por escolher');
                        }

                        $studentNo = $record->student?->number
                            ?? $record->registration?->student?->number
                            ?? $record->number
                            ?? null;

                        if (! $studentNo) {
                            return 'Sem horário definido';
                        }

                        $scheduleScope = Schedule::query()
                            ->where('id_subject', $record->id_subject)
                            ->whereIn('status', ['Aprovado', 'Aprovado DP'])
                            ->where('id_schoolyear', $record->registration?->id_schoolyear)
                            ->when($record->registration?->id_class, fn ($q, $id) => $q->whereHas('classes', fn ($qq) => $qq->where('classes.id', $id)));

                        $generalSchedules = (clone $scheduleScope)
                            ->where(function ($query): void {
                                $query->whereNull('shift')->orWhere('shift', '');
                            })
                            ->whereDoesntHave('students')
                            ->with(['weekday', 'timeperiod', 'room', 'teacher', 'students'])
                            ->get();

                        $turnSchedules = (clone $scheduleScope)
                            ->where('shift', 'like', 'Turno%')
                            ->get(['id']);

                        // Um horário sem turno só é geral quando não existem turnos alternativos.
                        if ($generalSchedules->isNotEmpty() && $turnSchedules->isEmpty()) {
                            $record->foundSchedulesForTurno = $generalSchedules;
                            $names = $generalSchedules->pluck('teacher.name')->filter()->unique()->values();

                            return $names->isNotEmpty()
                                ? 'Prof. '.$names->implode(' / ')
                                : 'Horário geral';
                        }

                        $candidates = Schedule::query()
                            ->where('id_subject', $record->id_subject)
                            ->whereIn('status', ['Aprovado', 'Aprovado DP'])
                            ->where('id_schoolyear', $record->registration?->id_schoolyear)
                            ->when($turnSchedules->isNotEmpty(), fn ($query) => $query->where('shift', 'like', 'Turno%'))
                            ->where(function ($query) use ($record, $studentNo) {
                                $query
                                    ->whereHas('students', fn ($q) => $q->where('students.id', $record->registration?->id_student))
                                    ->orWhere(function ($shiftQuery) use ($studentNo) {
                                        $shiftQuery
                                            ->whereNotNull('shift')
                                            ->where('shift', 'like', '%'.$studentNo.'%');
                                    });
                            })
                            ->when($record->registration?->id_class, fn ($q, $id) => $q->whereHas('classes', fn ($qq) => $qq->where('classes.id', $id)))
                            ->when($record->registration?->id_schoolyear, fn ($q, $sy) => $q->where('id_schoolyear', $sy))
                            ->with(['weekday', 'timeperiod', 'room', 'teacher', 'students'])
                            ->get()
                            ->filter(fn (Schedule $schedule): bool => blank($schedule->shift)
                                || preg_match(
                                    '/(^|\D)'.preg_quote((string) $studentNo, '/').'($|\D)/',
                                    (string) $schedule->shift,
                                ) === 1)
                            ->values();

                        if ($candidates->isEmpty()) {
                            return $turnSchedules->isNotEmpty() ? 'Sem Turno' : 'Sem horário definido';
                        }

                        $shiftName = (string) ($candidates->first()->shift ?? null);
                        $teacherId = $candidates->first()->id_teacher;
                        $classId = $record->registration?->id_class;
                        $schoolYearId = $record->registration?->id_schoolyear;

                        $siblings = Schedule::query()
                            ->where('id_subject', $record->id_subject)
                            ->where('id_teacher', $teacherId)
                            ->whereIn('status', ['Aprovado', 'Aprovado DP'])
                            ->when(
                                blank($shiftName),
                                fn ($q) => $q->whereHas('students', fn ($studentQuery) => $studentQuery->where('students.id', $record->registration?->id_student)),
                                fn ($q) => $q->where('shift', $shiftName),
                            )
                            ->when($classId, fn ($q) => $q->whereHas('classes', fn ($qq) => $qq->where('classes.id', $classId)))
                            ->when($schoolYearId, fn ($q) => $q->where('id_schoolyear', $schoolYearId))
                            ->with(['weekday', 'timeperiod', 'room', 'teacher', 'students'])
                            ->get();

                        $record->foundSchedulesForTurno = $siblings;

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

                            preg_match_all('/\b[A-Za-z]?\d+\b/', $turno, $shiftNumbers);

                            $nums = collect($shiftNumbers[0] ?? [])
                                ->merge($s->students
                                    ? $s->students->pluck('number')->map(fn ($n) => (string) $n)
                                    : collect())
                                ->map(fn ($number) => (string) $number)
                                ->unique()
                                ->values();
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
                    ->label('Escolher turno')
                    ->modalHeading('Escolher turno')
                    ->modalWidth('2xl')
                    ->extraModalWindowAttributes(['class' => 'maestro-schedule-modal'])
                    ->using(function (RegistrationSubject $record, array $data): void {
                        $user = Auth::user();
                        $studentId = $record->registration?->id_student;

                        $authorized = $user?->isGuardian()
                            ? $studentId !== null && (int) $studentId === $user->activeGuardianStudentId()
                            : $user?->hasRole('Aluno')
                                && $studentId !== null
                                && (int) $studentId === (int) $user->student?->id;

                        abort_unless($authorized, 403);

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
                                ->whereIn('status', ['Aprovado', 'Aprovado DP'])
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
                                ->whereIn('status', ['Aprovado', 'Aprovado DP'])
                                ->where('shift', $schedule->shift)
                                ->whereHas('classes', fn ($query) => $query->where('classes.id', $record->registration->id_class))
                                ->lockForUpdate()
                                ->get(['id', 'shift_limit']);

                            $enrolled = self::countEnrolledForShift(
                                $record,
                                $turnoSchedules->pluck('id'),
                                (string) $schedule->shift,
                            );
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

                        $hasTurnSchedules = Schedule::query()
                            ->where('id_subject', $record->id_subject)
                            ->whereIn('status', ['Aprovado', 'Aprovado DP'])
                            ->where('id_schoolyear', $record->registration?->id_schoolyear)
                            ->where('shift', 'like', 'Turno%')
                            ->whereHas('classes', fn ($query) => $query->where('classes.id', $record->registration?->id_class))
                            ->exists();

                        if (! $hasTurnSchedules) {
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
        $user = Auth::user();
        $query = parent::getEloquentQuery()
            ->whereHas(
                'registration.schoolyear',
                fn ($q) => $q->where('active', true)
            );

        if ($user?->isGuardian()) {
            $studentId = $user->activeGuardianStudentId();

            return $studentId
                ? $query->whereHas('registration', fn ($q) => $q->where('id_student', $studentId))
                : $query->whereRaw('0 = 1');
        }

        return $query->whereHas(
            'registration.student',
            fn ($q) => $q->where('user_id', Auth::id())
        );
    }

    private static function countEnrolledForShift(
        RegistrationSubject $record,
        mixed $scheduleIds,
        string $shift,
    ): int {
        $scheduleIds = collect($scheduleIds)->map(fn ($id) => (int) $id)->values();

        return RegistrationSubject::query()
            ->whereKeyNot($record->getKey())
            ->where(function (Builder $query) use ($record, $scheduleIds, $shift): void {
                $query->whereIn('id_schedule', $scheduleIds)
                    ->orWhere(function (Builder $legacyQuery) use ($record, $scheduleIds, $shift): void {
                        $legacyQuery
                            ->whereNull('id_schedule')
                            ->where('id_subject', $record->id_subject)
                            ->whereHas('registration', function (Builder $registrationQuery) use ($record): void {
                                $registrationQuery
                                    ->where('id_schoolyear', $record->registration->id_schoolyear)
                                    ->where('id_class', $record->registration->id_class);
                            })
                            ->where(function (Builder $shiftQuery) use ($scheduleIds, $shift): void {
                                $shiftQuery->where('shift', $shift)
                                    ->orWhereIn('shift', $scheduleIds->map(fn ($id) => (string) $id));
                            });
                    });
            })
            ->count();
    }

    private static function activeStudentContext(): string
    {
        $user = Auth::user();
        $studentId = $user?->isGuardian()
            ? $user->activeGuardianStudentId()
            : $user?->student?->id;

        if (! $studentId) {
            return 'Curso e turma';
        }

        $registrations = Registration::query()
            ->with(['course', 'class'])
            ->where('id_student', $studentId)
            ->whereHas('schoolyear', fn ($query) => $query->where('active', true))
            ->get();

        if ($registrations->isEmpty()) {
            return 'Curso e turma não definidos';
        }

        return 'Cursos: '.($registrations->pluck('course.name')->filter()->unique()->implode(', ') ?: 'Não definidos');
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
