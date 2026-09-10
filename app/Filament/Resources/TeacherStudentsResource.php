<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeacherStudentsResource\Pages;
use App\Models\RegistrationSubject;
use App\Models\Schedule;
use App\Models\SchoolYear;
use App\Models\TeacherSubject;
use Filament\Actions\ExportAction as ActionsExportAction;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Response;

class TeacherStudentsResource extends Resource
{
    // ✅ 1 linha = 1 Aluno/Disciplina (pivot)
    protected static ?string $model = RegistrationSubject::class;

    protected static ?string $navigationGroup = 'Horários';
    protected static ?string $navigationLabel = 'Os meus Alunos';
    protected static ?string $navigationIcon  = 'heroicon-o-academic-cap';
    protected static ?int    $navigationSort  = 3;


    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view teacher students') ?? false;
    }

    public static function getPluralLabel(): string
    {
        return 'Os meus Alunos';
    }

    public static function getEloquentQuery(): Builder
    {
        $teacherId  = auth()->user()?->teacher?->id ?? 0;
        $activeYear = SchoolYear::query()->where('active', true)->first();

        if (! $teacherId || ! $activeYear) {
            return parent::getEloquentQuery()->whereRaw('1=0');
        }

        // Disciplinas lecionadas por este professor no ano ativo
        $teacherSubjectIds = TeacherSubject::query()
            ->where('id_teacher', $teacherId)
            ->where('id_schoolyear', $activeYear->id)
            ->pluck('id_subject');

        // Se não houver disciplinas, retorna query vazia
        if ($teacherSubjectIds->isEmpty()) {
            return parent::getEloquentQuery()->whereRaw('1=0');
        }

        return parent::getEloquentQuery()
            ->whereIn('id_subject', $teacherSubjectIds)
            ->whereHas('registration', fn($q) => $q->where('id_schoolyear', $activeYear->id))
            ->where(function ($query) use ($teacherId, $activeYear) {
                $query->whereHas('selectedSchedule', function ($scheduleQuery) use ($teacherId, $activeYear) {
                    $scheduleQuery
                        ->where('id_teacher', $teacherId)
                        ->where('id_schoolyear', $activeYear->id)
                        ->where('status', 'Aprovado')
                        ->whereColumn('schedules.id_subject', 'registrations_subjects.id_subject');
                })->orWhereExists(function ($scheduleQuery) use ($teacherId, $activeYear) {
                    $scheduleQuery->selectRaw('1')
                        ->from('schedules_students')
                        ->join('schedules', 'schedules.id', '=', 'schedules_students.id_schedule')
                        ->join('schedules_classes', 'schedules_classes.id_schedule', '=', 'schedules.id')
                        ->join('registrations', 'registrations.id_student', '=', 'schedules_students.id_student')
                        ->whereColumn('registrations.id', 'registrations_subjects.id_registration')
                        ->whereColumn('registrations.id_class', 'schedules_classes.id_class')
                        ->whereColumn('schedules.id_subject', 'registrations_subjects.id_subject')
                        ->where('schedules.id_teacher', $teacherId)
                        ->where('schedules.id_schoolyear', $activeYear->id)
                         ->where('schedules.status', 'Aprovado');
                 })->orWhereExists(function ($scheduleQuery) use ($teacherId, $activeYear) {
                     // Horário criado para a turma inteira, sem pivot individual do aluno.
                     $scheduleQuery->selectRaw('1')
                         ->from('schedules')
                          ->join('schedules_classes', 'schedules_classes.id_schedule', '=', 'schedules.id')
                          ->join('registrations', 'registrations.id', '=', 'registrations_subjects.id_registration')
                          ->whereColumn('schedules_classes.id_class', 'registrations.id_class')
                         ->whereColumn('schedules.id_subject', 'registrations_subjects.id_subject')
                         ->where('schedules.id_teacher', $teacherId)
                         ->where('schedules.id_schoolyear', $activeYear->id)
                         ->whereIn('schedules.status', ['Aprovado', 'Aprovado DP']);
                 });
            })
            ->with([
                'subject',
                'registration.student',  // ->number (ou process_number)
                'registration.class',    // ->name
                'selectedSchedule',
            ]);
    }




    public static function exportTeacherStudents(?Collection $records = null): StreamedResponse
    {
        $teacherId = auth()->user()?->teacher?->id ?? 0;

        // Obter registos (selecionados vs todos no escopo)
        if ($records) {
            $regSubs = $records->load(['registration.student', 'registration.class', 'subject']);
        } else {
            $regSubs = static::getEloquentQuery()->get();
        }

        // 3) Helper para resolver o turno (mesma lógica usada na tabela)
        $resolveShift = function (RegistrationSubject $record) use ($teacherId): ?string {
            $classId   = $record->registration?->id_class;
            $subjectId = $record->id_subject;
            $studentId = $record->registration?->id_student;

            if ($teacherId && $classId && $subjectId && $studentId) {
            $schedule = $record->selectedSchedule;

                if ($schedule
                    && $schedule->id_teacher === $teacherId
                    && $schedule->id_subject === $subjectId
                    && $schedule->status === 'Aprovado'
                    && ! blank($schedule->shift)) {
                    return (string) $schedule->shift;
                }

                $schedule = Schedule::query()
                    ->where('id_teacher', $teacherId)
                    ->where('id_subject', $subjectId)
                    ->whereIn('status', ['Aprovado', 'Aprovado DP'])
                    ->whereHas('classes', fn($q) => $q->where('classes.id', $classId))
                    ->orderBy('id')
                    ->first();

                if ($schedule && ! blank($schedule->shift)) {
                    return (string) $schedule->shift;
                }

                $schedule = Schedule::query()
                    ->where('id_teacher', $teacherId)
                    ->where('id_subject', $subjectId)
                    ->whereIn('status', ['Aprovado', 'Aprovado DP'])
                    ->whereHas('classes', fn($q) => $q->where('classes.id', $classId))
                    ->whereHas('students', fn($q) => $q->where('students.id', $studentId))
                    ->orderBy('id')
                    ->first();

                if ($schedule && ! blank($schedule->shift)) {
                    return (string) $schedule->shift;
                }
            }

            // fallback: pivot
            $regSub = RegistrationSubject::query()
                ->where('id_registration', $record->id_registration)
                ->where('id_subject', $record->id_subject)
                ->first();

            if ($regSub) {
                $scheduleId = $regSub->id_schedule ?? null;

                if ($scheduleId) {
                    $sch = Schedule::find($scheduleId);
                    if ($sch && ! blank($sch->shift)) {
                        return (string) $sch->shift;
                    }
                }

                foreach (['shift', 'turno', 'turn'] as $cand) {
                    $val = $regSub->{$cand} ?? null;
                    if (! blank($val) && ! ctype_digit((string) $val)) {
                        return (string) $val;
                    }
                }
            }

            return null;
        };

        // 4) Stream .txt pipe-separated
        $now = now()->format('Y-m-d_H-i');
        $filename = "os-meus-alunos-{$now}.txt";

        return response()->streamDownload(function () use ($regSubs, $resolveShift) {
            $h = fopen('php://output', 'w');

            // cabeçalho
            $header = ['Aluno', 'Disciplina', 'Turma', 'Turno'];
            fputs($h, implode(';', array_map(fn($v) => '' . str_replace('', '', $v) . '', $header)) . "\n");

            foreach ($regSubs as $r) {
                $aluno  = (string) ($r->registration?->student?->name ?? ';');
                $disc   = (string) ($r->subject?->name ?? ';');
                $turma  = (string) ($r->registration?->class?->name ?? ';');
                $turno  = (string) ($resolveShift($r) ?? ';');

                $linha = [$aluno, $disc, $turma, $turno];
                $linha = array_map(fn($v) => '' . str_replace('', '', (string) $v) . '', $linha);

                fputs($h, implode(';', $linha) . "\n");
            }

            fclose($h);
        }, $filename, [
            'Content-Type'        => 'text/plain; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id_registration')
            ->columns([
                // Aluno
                TextColumn::make('registration.student.name')
                    ->label('Aluno')
                    ->description(
                        fn(RegistrationSubject $r) =>
                        'Nº ' . ($r->registration?->student?->number ?? '—')
                    )
                    ->sortable()
                    ->searchable(),

                // Disciplina
                TextColumn::make('subject.name')
                    ->label('Disciplina')
                    ->sortable()
                    ->searchable(),

                // Turma
                TextColumn::make('registration.class.name')
                    ->label('Turma')
                    ->sortable()
                    ->searchable(),

                // Turno (Schedule do professor → fallback pivot)
                TextColumn::make('shift')
                    ->label('Turno')
                    ->state(fn(RegistrationSubject $record) => self::resolveShift($record) ?? '—')
                    ->searchable()
                    ->wrap(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('id_subject')
                    ->label('Disciplina')
                    ->relationship('subject', 'name')
                    ->preload()
                    ->searchable(),

                Tables\Filters\SelectFilter::make('id_class')
                    ->label('Turma')
                    ->options(fn() => DB::table('classes')->pluck('name', 'id')->toArray())
                    ->query(
                        fn(Builder $q, array $data) =>
                        ! empty($data['value'])
                            ? $q->whereHas('registration', fn($qq) => $qq->where('id_class', $data['value']))
                            : $q
                    ),
            ])
            ->actions([])
            ->headerActions([
                Tables\Actions\Action::make('exportar_tudo')
                    ->label('Exportar')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->action(fn() => static::exportTeacherStudents()),
            ])
            ->bulkActions([

                BulkAction::make('exportar_selecionados')
                    ->label('Exportar selecionados')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->action(fn(Collection $records) => static::exportTeacherStudents($records)),
            ]);
    }

    /**
     * Resolve o texto do turno:
     *  1) Usa o Schedule escolhido pelo aluno no pivot.
     *  2) Fallback: usa o id_schedule ou campo textual do pivot.
     */
    protected static function resolveShift(RegistrationSubject $record): ?string
    {
        $teacherId = auth()->user()?->teacher?->id ?? 0;
        $classId   = $record->registration?->id_class;
        $subjectId = $record->id_subject;
        $studentId = $record->registration?->id_student;

        // 1) Horário escolhido pelo aluno no pivot
        if ($teacherId && $classId && $subjectId && $studentId) {
            $schedule = $record->selectedSchedule;

            if ($schedule
                && $schedule->id_teacher === $teacherId
                && $schedule->id_subject === $subjectId
                && $schedule->status === 'Aprovado'
                && ! blank($schedule->shift)) {
                return (string) $schedule->shift;
            }

            $schedule = Schedule::query()
                ->where('id_teacher', $teacherId)
                ->where('id_subject', $subjectId)
                ->whereIn('status', ['Aprovado', 'Aprovado DP'])
                ->whereHas('classes', fn($q) => $q->where('classes.id', $classId))
                ->orderBy('id')
                ->first();

            if ($schedule && ! blank($schedule->shift)) {
                return (string) $schedule->shift;
            }

            $schedule = Schedule::query()
                ->where('id_teacher', $teacherId)
                ->where('id_subject', $subjectId)
                ->where('status', 'Aprovado')
                ->whereHas('classes', fn($q) => $q->where('classes.id', $classId))
                ->whereHas('students', fn($q) => $q->where('students.id', $studentId))
                ->orderBy('id')
                ->first();

            if ($schedule && ! blank($schedule->shift)) {
                return (string) $schedule->shift;
            }
        }

        // 2) Fallback no pivot: id_schedule → texto
        $regSub = RegistrationSubject::query()
            ->where('id_registration', $record->id_registration)
            ->where('id_subject', $subjectId)
            ->first();

        if ($regSub) {
            // (a) id_schedule explícito
            $scheduleId = $regSub->id_schedule ?? null;

            if ($scheduleId) {
                $sch = Schedule::find($scheduleId);
                if ($sch && ! blank($sch->shift)) {
                    return (string) $sch->shift;
                }
            }

            // (c) texto do turno no pivot
            foreach (['shift', 'turno', 'turn'] as $cand) {
                $val = $regSub->{$cand} ?? null;
                if (! blank($val) && ! ctype_digit((string) $val)) {
                    return (string) $val;
                }
            }
        }

        return null;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeacherStudents::route('/'),
        ];
    }
}
