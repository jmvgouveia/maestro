<?php

namespace App\Filament\Resources\ScheduleResource\Traits;

use App\Models\Schedule;
use App\Models\TeacherHourCounter;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

trait HourCounter {

    protected function hoursCounterUpdate(?Schedule $schedule, Bool $plusOrMinus): void
    {
        if (! $schedule) {
            return;
        }

        try {
            DB::transaction(function () use ($schedule) {
                $counter = TeacherHourCounter::where('id_teacher', $schedule->id_teacher)
                    ->where('id_schoolyear', $schedule->id_schoolyear)
                    ->first();

                if (! $counter) {
                    return;
                }

                $teacher = $schedule->teacher()->first();
                $teachingReduction = $teacher?->positions()
                    ->wherePivot('id_schoolyear', $schedule->id_schoolyear)
                    ->sum('positions.reduction_l') ?? 0;
                $teachingReduction += $teacher?->timeReductions()
                    ->wherePivot('id_schoolyear', $schedule->id_schoolyear)
                    ->sum('time_reductions.value_l') ?? 0;

                $nonTeachingReduction = $teacher?->positions()
                    ->wherePivot('id_schoolyear', $schedule->id_schoolyear)
                    ->sum('positions.reduction_nl') ?? 0;
                $nonTeachingReduction += $teacher?->timeReductions()
                    ->wherePivot('id_schoolyear', $schedule->id_schoolyear)
                    ->sum('time_reductions.value_nl') ?? 0;

                $approvedSchedules = Schedule::query()
                    ->with('subject')
                    ->where('id_teacher', $schedule->id_teacher)
                    ->where('id_schoolyear', $schedule->id_schoolyear)
                    ->whereIn('status', ['Aprovado', 'Aprovado DP'])
                    ->get();

                $nonTeachingSchedules = $approvedSchedules->filter(
                    fn (Schedule $item): bool => strtolower(trim($item->subject?->type ?? 'letiva')) === 'não letiva'
                        || strtolower(trim($item->subject?->type ?? 'letiva')) === 'nao letiva'
                )->count();

                $teachingSchedules = $approvedSchedules->count() - $nonTeachingSchedules;

                $counter->teaching_load = 22 - $teachingReduction - $teachingSchedules;
                $counter->non_teaching_load = 4 - $nonTeachingReduction - $nonTeachingSchedules;
                $counter->workload = $counter->teaching_load + $counter->non_teaching_load;
                $counter->save();
            });
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erro ao atualizar a carga horária do professor')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

}
