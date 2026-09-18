<?php

namespace App\Filament\Resources\ScheduleResource\Traits;

use App\Models\RoomBlockedHours;
use App\Models\Schedule;
use App\Models\ScheduleRequest;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherHourCounter;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use App\Models\Student;
use Illuminate\Validation\ValidationException;

trait ChecksScheduleConflicts
{

    protected function timesOverlap(string $startA, string $endA, string $startB, string $endB): bool
    {
        return strtotime($startA) < strtotime($endB) && strtotime($startB) < strtotime($endA);
    }



    private function checkRoomBlocked(int $idRoom, int $idWeekday, int $idTimePeriod): void
    {
        $bloqueio = RoomBlockedHours::where('id_room', $idRoom)
            ->where('id_weekday', $idWeekday)
            ->where('id_timeperiod', $idTimePeriod)
            ->first();

        if ($bloqueio) {
            Notification::make()
                ->title('Sala bloqueada')
                ->body("Não é possível marcar ou trocar nesta sala. Motivo: {$bloqueio->description}")
                ->danger()
                ->persistent()
                ->send();

            throw new Halt("Sala bloqueada: {$bloqueio->description}");
        }
    }




    protected function checkScheduleConflictsAndAvailability(array $data, ?int $ignoreId = null): void
    {
        $subject = Subject::find($data['id_subject']);
        $tipo = strtolower($subject->type ?? 'letiva');

        $teacher = ! empty($data['id_teacher'])
            ? Teacher::find($data['id_teacher'])
            : ($this->record?->teacher ?? Teacher::where('id_user', Filament::auth()->id())->first());

        if (!$teacher) {
            Notification::make()
                ->title('Erro')
                ->body('Professor não encontrado.')
                ->danger()
                ->persistent()
                ->send();

            throw new Halt('Professor não encontrado.');
        }

        if (!empty($data['students']) && is_array($data['students'])) {
            foreach ($data['students'] as $studentId) {
                $this->checkStudentScheduleConflict($studentId, $data['id_weekday'], $data['id_timeperiod'], $ignoreId);
            }
        }

        $this->checkTeacherScheduleConflict($teacher->id, $data['id_weekday'], $data['id_timeperiod'], $ignoreId);


        if ($subject && !in_array(strtolower((string) $subject->name), ['reunião', 'tee'])) {
            $this->checkRoomScheduleConflict(
                $data['id_room'],
                $data['id_weekday'],
                $data['id_timeperiod'],
                $ignoreId
            );
        }
        $this->checkRoomBlocked($data['id_room'], $data['id_weekday'], $data['id_timeperiod']);
        $this->checkHoursDaily($teacher->id, $data['id_weekday']);
        if (! Filament::auth()->user()?->hasRole('Gestor de Horários')) {
            $this->checkWorkload($teacher->id, $tipo);
        }
    }



    // private function checkTeacherScheduleConflict(int $idTeacher, int $weekday, int $timeperiod, ?int $ignoreId = null): void
    // {
    //     $query = Schedule::where('id_teacher', $idTeacher)
    //         ->where('id_weekday', $weekday)
    //         ->where('id_timeperiod', $timeperiod)
    //         ->where('status', '!=', 'Recusado DP')
    //         ->where('status', '!=', 'Eliminado')
    //         ->where('id_schoolyear', SchoolYear::where('active', true)->value('id'))
    //         ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId));

    //     if ($query->exists()) {
    //         Notification::make()
    //             ->title('Conflito de horário detetado')
    //             ->body("Já tem uma atividade marcada neste horário.")
    //             ->warning()
    //             ->persistent()
    //             ->send();

    //         throw new Halt('Erro: O professor já tem uma atividade neste horário.');
    //     }
    // }

    private function checkTeacherScheduleConflict(int $idTeacher, int $weekday, int $idTimePeriod, ?int $ignoreId = null): void
    {
        $newPeriod = \App\Models\TimePeriod::find($idTimePeriod);
        if (!$newPeriod) return;

        $existing = Schedule::where('id_teacher', $idTeacher)
            ->where('id_weekday', $weekday)
            ->where('id_schoolyear', SchoolYear::where('active', true)->value('id'))
            ->whereNotIn('status', ['Recusado DP', 'Eliminado'])
            ->with('timePeriod')
            ->get();

        foreach ($existing as $schedule) {
            // Ignora se for o mesmo horário que está a ser editado ou pedido de troca no mesmo slot
            if (
                $ignoreId &&
                $schedule->id === $ignoreId &&
                $schedule->id_timeperiod === $idTimePeriod
            ) {
                continue;
            }

            if ($schedule->timePeriod && $this->timesOverlap(
                $newPeriod->start_time,
                $newPeriod->end_time,
                $schedule->timePeriod->start_time,
                $schedule->timePeriod->end_time
            )) {
                Notification::make()
                    ->title('Conflito de horário detetado')
                    ->body("Já tem uma atividade marcada neste horário.")
                    ->warning()
                    ->persistent()
                    ->send();
                throw new Halt('Erro: O professor já tem uma atividade neste horário.');
            }
        }
    }


    // public function checkStudentScheduleConflict(string|int $studentNumber, int $weekday, int $timeperiod, ?int $ignoreId = null): void
    // {
    //     // 1. Obter ID do aluno a partir do número
    //     $student = Student::where('id', $studentNumber)->first();

    //     if (!$student) {
    //         Notification::make()
    //             ->title('Aluno não encontrado')
    //             ->body("Não foi encontrado aluno com o número {$studentNumber}.")
    //             ->danger()
    //             ->send();

    //         throw new Halt("Erro: O Aluno {$student->name} já tem uma atividade neste horário.");
    //     }

    //     // 2. Verificar se esse aluno tem marcação nesse dia/período
    //     $conflict = Schedule::whereHas(
    //         'students',
    //         fn($q) =>
    //         $q->where('id_student', $student->id)
    //     )
    //         ->where('id_weekday', $weekday)
    //         ->where('id_timeperiod', $timeperiod)
    //         ->where('id_schoolyear', SchoolYear::where('active', true)->value('id'))
    //         ->whereNotIn('status', ['Recusado DP', 'Eliminado'])
    //         ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
    //         ->exists();

    //     // 3. Se existir conflito, notificar e parar
    //     if ($conflict) {
    //         Notification::make()
    //             ->title('Conflito de horário detetado')
    //             ->body("O aluno {$student->name} já tem uma atividade neste horário.")
    //             ->warning()
    //             ->persistent()
    //             ->send();

    //         throw new Halt("Erro: O Aluno {$student->name} já tem uma atividade neste horário.");
    //     }
    // }

    public function checkStudentScheduleConflict(string|int $studentNumber, int $weekday, int $idTimePeriod, ?int $ignoreId = null): void
    {
        $student = Student::find($studentNumber);
        if (!$student) {
            Notification::make()
                ->title('Aluno não encontrado')
                ->body("Aluno com o ID {$studentNumber} não encontrado.")
                ->danger()
                ->send();
            throw new Halt("Erro: aluno não encontrado.");
        }

        $newPeriod = \App\Models\TimePeriod::find($idTimePeriod);
        if (!$newPeriod) return;

        $conflicts = Schedule::whereHas('students', fn($q) => $q->where('id_student', $student->id))
            ->where('id_weekday', $weekday)
            ->where('id_schoolyear', SchoolYear::where('active', true)->value('id'))
            ->whereNotIn('status', ['Recusado DP', 'Eliminado'])
            ->with('timePeriod')
            ->get();

        foreach ($conflicts as $schedule) {
            if (
                $ignoreId &&
                $schedule->id === $ignoreId &&
                $schedule->id_timeperiod === $idTimePeriod
            ) {
                continue;
            }

            if ($schedule->timePeriod && $this->timesOverlap(
                $newPeriod->start_time,
                $newPeriod->end_time,
                $schedule->timePeriod->start_time,
                $schedule->timePeriod->end_time
            )) {
                Notification::make()
                    ->title('Conflito de horário detetado')
                    ->body("O aluno {$student->name} já tem uma atividade neste horário.")
                    ->warning()
                    ->persistent()
                    ->send();
                throw new Halt("Erro: conflito de horário com o aluno {$student->name}.");
            }
        }
    }


    // private function checkRoomScheduleConflict(int $idRoom, int $weekday, int $timeperiod, ?int $ignoreId = null): void
    // {


    //     $query = Schedule::where('id_room', $idRoom)
    //         ->where('id_weekday', $weekday)
    //         ->where('id_timeperiod', $timeperiod)
    //         ->where('id_schoolyear', SchoolYear::where('active', true)->value('id'))
    //         ->whereNotIn('status', ['Recusado DP', 'Eliminado'])
    //         ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId));


    //     $this->conflictingSchedule = $query->with('teacher')->first();

    //     if ($this->conflictingSchedule) {
    //         $prof = $this->conflictingSchedule->teacher->name ?? 'outro professor';

    //         Notification::make()
    //             ->title("Conflito de horário detetado")
    //             ->body("Já existe um agendamento com o(a) professor(a) {$prof} nesta sala e horário. Altere o horário, a sala, ou solicite uma troca.")
    //             ->warning()
    //             ->persistent()
    //             ->send();

    //         throw new Halt('Erro: Conflito de horário na sala.');
    //     }
    // }



    private function checkRoomScheduleConflict(int $idRoom, int $weekday, int $idTimePeriod, ?int $ignoreId = null): void
    {
        $newPeriod = \App\Models\TimePeriod::find($idTimePeriod);
        if (!$newPeriod) return;

        $schedules = Schedule::where('id_room', $idRoom)
            ->where('id_weekday', $weekday)
            ->where('id_schoolyear', SchoolYear::where('active', true)->value('id'))
            ->whereNotIn('status', ['Recusado DP', 'Eliminado'])
            ->with(['teacher', 'timePeriod'])
            ->get();

        foreach ($schedules as $schedule) {
            if (
                $ignoreId &&
                $schedule->id === $ignoreId &&
                $schedule->id_timeperiod === $idTimePeriod
            ) {
                continue;
            }

            if ($schedule->timePeriod && $this->timesOverlap(
                $newPeriod->start_time,
                $newPeriod->end_time,
                $schedule->timePeriod->start_time,
                $schedule->timePeriod->end_time
            )) {
                $this->conflictingSchedule = $schedule;


                $prof = $schedule->teacher->name ?? 'outro professor';
                Notification::make()
                    ->title("Conflito de horário detetado")
                    ->body("Já existe um agendamento com {$prof} nesta sala e horário.")
                    ->warning()
                    ->persistent()
                    ->send();
                throw new Halt('Erro: conflito de horário na sala.');
            }
        }
    }



    private function checkWorkload(int $idTeacher, string $tipo): void
    {
        $activeSchoolYear = SchoolYear::where('active', true)->first();

        if (!$activeSchoolYear) {
            Notification::make()
                ->title('Ano letivo ativo não encontrado')
                ->body('Não há ano letivo ativo definido no sistema.')
                ->danger()
                ->persistent()
                ->send();

            throw new Halt('Ano letivo ativo não encontrado.');
        }

        $counter = TeacherHourCounter::where('id_teacher', $idTeacher)
            ->where('id_schoolyear', $activeSchoolYear->id)
            ->first();

        if (!$counter) {
            Notification::make()
                ->title('Contador de horas não encontrado')
                ->body("Contador de horas não encontrado para o professor.")
                ->warning()
                ->persistent()
                ->send();

            throw new Halt('Contador de horas não encontrado para o professor.');
        }

        if ($tipo === 'nao letiva') {
            if ($counter->non_teaching_load <= 0) {
                Notification::make()
                    ->title('Sem horas disponíveis')
                    ->body('Sem horas disponíveis na componente não letiva.')
                    ->warning()
                    ->persistent()
                    ->send();

                throw new Halt('Sem horas disponíveis na componente não letiva.');
            }
        } else {
            if ($counter->teaching_load <= 0) {
                Notification::make()
                    ->title('Sem horas disponíveis')
                    ->body('Sem horas disponíveis na componente letiva.')
                    ->warning()
                    ->persistent()
                    ->send();

                throw new Halt('Sem horas disponíveis na componente letiva.');
            }
        }
    }

    private function checkHoursDaily(int $idTeacher, int $weekday): void
    {
        $schoolYearId = SchoolYear::where('active', true)->value('id');

        $schedules = Schedule::where('id_teacher', $idTeacher)
            ->where('id_weekday', $weekday)
            ->where('id_schoolyear', $schoolYearId)
            ->whereNotIn('status', ['Recusado DP', 'Eliminado'])
            ->with('timePeriod')
            ->get()
            ->sortBy(fn($s) => strtotime($s->timePeriod->start_time ?? '00:00:00'))
            ->values();

        $maxConsecutivas = 0;
        $atuais = 0;
        $anteriorFim = null;

        foreach ($schedules as $schedule) {
            $start = strtotime($schedule->timePeriod->start_time);
            $end = strtotime($schedule->timePeriod->end_time);

            if ($anteriorFim && $start === $anteriorFim) {
                $atuais++;
            } else {
                $atuais = 1;
            }

            $maxConsecutivas = max($maxConsecutivas, $atuais);
            $anteriorFim = $end;
        }

        if ($maxConsecutivas > 6) {
            Notification::make()
                ->title('Limite diário excedido')
                ->body('Este professor já tem 6 marcações seguidas neste dia. Deve haver um intervalo.')
                ->danger()
                ->persistent()
                ->send();

            throw new Halt('Erro: marcações consecutivas excedem o limite diário.');
        }
    }
}
